<?php

namespace App\Services\Whatsapp;

use App\Jobs\Whatsapp\RunBotFlowJob;
use App\Models\Account;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappBotCredential;
use App\Models\WhatsappBotFlow;
use App\Models\WhatsappBotSession;
use App\Models\WhatsappCreditBalance;
use App\Models\WhatsappCreditLedger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bot Builder's flow-execution engine (screenshots 54-62) — a small
 * conversation-state machine with no precedent anywhere else in this app
 * (confirmed before building this: no whatsapp_*_sessions/state table or
 * cache-based equivalent existed). Always run from inside RunBotFlowJob
 * (queued), never called directly from WebhookController — same reasoning
 * SendAutoReplyJob/RejectCallJob's own docblocks already document: a flow
 * can send several messages in one burst, each a bridge round-trip, and
 * that must never run inline inside the webhook's own 10s-timeout request.
 *
 * flow_definition is vue-flow's own {nodes:[{id,type,position,data}],
 * edges:[{id,source,target,sourceHandle?}]} shape, stored with zero
 * transformation. Only the v1 node set actually executes (start, text,
 * input, buttons, list, condition, set_variable, webhook, ai_reply, wait,
 * jump, end) — every other palette item in the UI is locked and can never
 * reach a saved flow_definition, but an unknown type is still handled
 * defensively (logged + skipped) so a hand-edited/imported flow can never
 * hard-crash this engine.
 */
class BotFlowExecutor
{
    private const MAX_STEPS = 50; // guards against a malformed flow (e.g. a jump cycle with no blocking node)

    public function __construct(private readonly BridgeClient $bridge) {}

    public function start(WhatsappBotFlow $bot, Conversation $conversation): void
    {
        $session = WhatsappBotSession::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'bot_flow_id' => $bot->id,
                'current_node_id' => null,
                'variables' => [],
                'status' => WhatsappBotSession::STATUS_ACTIVE,
                'started_at' => now(),
                'completed_at' => null,
            ],
        );

        $bot->increment('run_count');

        $startNode = $this->findNodeByType($bot->flow_definition['nodes'] ?? [], 'start');
        if (! $startNode) {
            return;
        }

        $this->advance($session, $bot, $startNode['id']);
    }

    /**
     * `cancel`/`stop` is a cheap, deliberate escape hatch — without it a
     * user has no way out of a flow once they've triggered one, since a bot
     * session claims every subsequent message from that conversation ahead
     * of chatbot rules/autoresponder.
     */
    public function resume(WhatsappBotSession $session, string $inboundBody): void
    {
        $bot = $session->botFlow;
        if (! $bot) {
            $session->update(['status' => WhatsappBotSession::STATUS_ABANDONED, 'completed_at' => now()]);

            return;
        }

        $trimmed = trim($inboundBody);

        if (in_array(mb_strtolower($trimmed), ['cancel', 'stop'], true)) {
            $session->update(['status' => WhatsappBotSession::STATUS_ABANDONED, 'completed_at' => now()]);

            return;
        }

        $nodes = collect($bot->flow_definition['nodes'] ?? [])->keyBy('id');
        $currentNode = $nodes->get($session->current_node_id);

        if (! $currentNode) {
            // Flow was edited/deleted after this session started on it.
            $session->update(['status' => WhatsappBotSession::STATUS_ABANDONED, 'completed_at' => now()]);

            return;
        }

        // Always available regardless of node type, e.g. so an AI Reply
        // node's default prompt ({{last_message}}) can react to whatever
        // the user just said without requiring an explicit Input node first.
        $this->setVariable($session, 'last_message', $trimmed);

        $variableName = $currentNode['data']['variable_name'] ?? null;

        if ($currentNode['type'] === 'input' && $variableName) {
            $fieldType = $currentNode['data']['field_type'] ?? 'text';

            if (! $this->validateInput($fieldType, $trimmed)) {
                $this->sendAndDecrement(
                    $bot->channel, $bot->channel?->account, $session->conversation, 'text',
                    "That doesn't look right. ".($currentNode['data']['body'] ?? ''), null, "bot:{$bot->id}",
                );

                return; // stay on the same node, wait for a valid reply
            }

            $this->setVariable($session, $variableName, $trimmed);
        } elseif (in_array($currentNode['type'], ['buttons', 'list'], true) && $variableName) {
            $this->setVariable($session, $variableName, $trimmed);
        }

        $nextId = $this->nextNodeId($bot->flow_definition['edges'] ?? [], $currentNode['id']);
        $this->advance($session, $bot, $nextId);
    }

    /**
     * Re-entry point for the `wait` node's delayed continuation — advances
     * from an explicit node id rather than reacting to an inbound message.
     */
    public function continueFrom(WhatsappBotSession $session, string $nodeId): void
    {
        $bot = $session->botFlow;
        if (! $bot || $session->status !== WhatsappBotSession::STATUS_ACTIVE) {
            return;
        }

        $this->advance($session, $bot, $nodeId);
    }

    private function advance(WhatsappBotSession $session, WhatsappBotFlow $bot, ?string $nodeId): void
    {
        $nodes = collect($bot->flow_definition['nodes'] ?? [])->keyBy('id');
        $edges = $bot->flow_definition['edges'] ?? [];

        $channel = $bot->channel;
        $conversation = $session->conversation;
        $account = $channel?->account;

        if (! $channel || ! $conversation || ! $account) {
            return;
        }

        $steps = 0;

        while ($nodeId !== null && $steps < self::MAX_STEPS) {
            $steps++;
            $node = $nodes->get($nodeId);

            if (! $node) {
                return;
            }

            $type = $node['type'] ?? null;
            $data = $node['data'] ?? [];
            // update() mutates $session's in-memory attributes too, so this
            // always reflects the latest variables without a re-query.
            $variables = $session->variables ?? [];

            switch ($type) {
                case 'start':
                    $nodeId = $this->nextNodeId($edges, $nodeId);
                    continue 2;

                case 'text':
                    $body = $this->interpolate($data['body'] ?? null, $variables);
                    if (! $this->sendAndDecrement($channel, $account, $conversation, 'text', $body, null, "bot:{$bot->id}")) {
                        return;
                    }
                    $session->update(['current_node_id' => $nodeId]);
                    $nodeId = $this->nextNodeId($edges, $nodeId);
                    continue 2;

                case 'input':
                    $body = $this->interpolate($data['body'] ?? null, $variables);
                    if (! $this->sendAndDecrement($channel, $account, $conversation, 'text', $body, null, "bot:{$bot->id}")) {
                        return;
                    }
                    $session->update(['current_node_id' => $nodeId]);

                    return; // blocking — wait for the reply

                case 'buttons':
                    $config = ['buttons' => array_values(array_filter($data['buttons'] ?? []))];
                    $body = $this->interpolate($data['body'] ?? null, $variables);
                    if (! $this->sendAndDecrement($channel, $account, $conversation, 'buttons', $body, $config, "bot:{$bot->id}")) {
                        return;
                    }
                    $session->update(['current_node_id' => $nodeId]);

                    return; // blocking

                case 'list':
                    $config = ['button_text' => $data['button_text'] ?? 'Choose', 'sections' => $data['sections'] ?? []];
                    $body = $this->interpolate($data['body'] ?? null, $variables);
                    if (! $this->sendAndDecrement($channel, $account, $conversation, 'list', $body, $config, "bot:{$bot->id}")) {
                        return;
                    }
                    $session->update(['current_node_id' => $nodeId]);

                    return; // blocking

                case 'condition':
                    $actual = $variables[$data['variable_name'] ?? ''] ?? null;
                    $result = $this->evaluateCondition($actual, $data['operator'] ?? 'equals', $data['value'] ?? null);
                    $nodeId = $this->nextNodeId($edges, $nodeId, $result ? 'true' : 'false');
                    continue 2;

                case 'set_variable':
                    $this->setVariable($session, $data['variable_name'] ?? '', $this->interpolate($data['value'] ?? null, $variables) ?? '');
                    $nodeId = $this->nextNodeId($edges, $nodeId);
                    continue 2;

                case 'webhook':
                    $this->runWebhook($data, $session, $variables);
                    $nodeId = $this->nextNodeId($edges, $nodeId);
                    continue 2;

                case 'ai_reply':
                    $this->runAiReply($data, $bot, $channel, $account, $conversation, $session, $variables);
                    $nodeId = $this->nextNodeId($edges, $nodeId);
                    continue 2;

                case 'wait':
                    $session->update(['current_node_id' => $nodeId]);
                    $nextId = $this->nextNodeId($edges, $nodeId);
                    if ($nextId !== null) {
                        RunBotFlowJob::dispatch($conversation->id, RunBotFlowJob::MODE_CONTINUE, null, null, $nextId)
                            ->delay(now()->addSeconds(max(1, (int) ($data['seconds'] ?? 1))));
                    }

                    return;

                case 'jump':
                    $nodeId = $data['target_node_id'] ?? null;
                    continue 2;

                case 'end':
                    $session->update(['status' => WhatsappBotSession::STATUS_COMPLETED, 'current_node_id' => null, 'completed_at' => now()]);
                    $bot->increment('completion_count');

                    return;

                default:
                    Log::warning('Bot flow: unknown/unsupported node type, skipping', ['type' => $type, 'bot_flow_id' => $bot->id]);
                    $nodeId = $this->nextNodeId($edges, $nodeId);
                    continue 2;
            }
        }
    }

    private function setVariable(WhatsappBotSession $session, string $name, string $value): void
    {
        if ($name === '') {
            return;
        }

        $variables = $session->variables ?? [];
        $variables[$name] = $value;
        $session->update(['variables' => $variables]);
    }

    /**
     * Same credit-check-then-decrement precedent as SendAutoReplyJob — a
     * flow burst can send several messages per inbound trigger, so this is
     * checked before *each* one; hitting 0 mid-burst halts the whole
     * advance() silently (the session stays put, current_node_id already
     * points at the last completed node) rather than throwing.
     */
    private function sendAndDecrement(
        ?Channel $channel, ?Account $account, Conversation $conversation,
        string $type, ?string $body, ?array $interactiveConfig, string $sentBy,
    ): bool {
        if (! $channel || ! $account || $channel->status !== Channel::STATUS_CONNECTED) {
            return false;
        }

        $balance = WhatsappCreditBalance::forAccount($account);
        if ($balance->credits_remaining < 1) {
            return false;
        }

        try {
            $this->bridge->sendMessage($channel->id, $conversation->contact_phone, $type, $body, null, null, $interactiveConfig);

            $conversation->messages()->create([
                'direction' => Message::DIRECTION_OUT,
                'type' => $type,
                'body' => $body,
                'status' => 'sent',
                'sent_by' => $sentBy,
            ]);
            $conversation->update(['last_message_at' => now()]);

            WhatsappCreditLedger::record($account, -1, WhatsappCreditLedger::REASON_MESSAGE_SENT);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Bot flow message failed to send', [
                'channel_id' => $channel->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Best-effort, non-fatal — mirrors RejectCallJob's precedent for an
     * outbound side-effect that shouldn't ever block the flow itself.
     */
    private function runWebhook(array $data, WhatsappBotSession $session, array $variables): void
    {
        $url = $data['url'] ?? null;
        if (! $url) {
            return;
        }

        try {
            $bodyTemplate = $this->interpolate($data['body_template'] ?? null, $variables);
            $payload = $bodyTemplate ? (json_decode($bodyTemplate, true) ?? ['body' => $bodyTemplate]) : [];

            $response = Http::timeout(5)->post($url, $payload);

            $responseVariable = $data['response_variable'] ?? null;
            if ($responseVariable) {
                $this->setVariable($session, $responseVariable, $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('Bot flow webhook node failed', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AI Reply — the flow author's own API key (WhatsappBotCredential),
     * never a platform-held one; scoped to the bot's own account so a
     * credential can't be borrowed cross-tenant. Best-effort like the
     * webhook node: a failed/unconfigured call just skips the send rather
     * than breaking the flow.
     */
    private function runAiReply(
        array $data, WhatsappBotFlow $bot, ?Channel $channel, ?Account $account,
        Conversation $conversation, WhatsappBotSession $session, array $variables,
    ): void {
        $credentialId = $data['credential_id'] ?? null;
        if (! $credentialId) {
            return;
        }

        $credential = WhatsappBotCredential::where('account_id', $bot->account_id)->find($credentialId);
        if (! $credential) {
            return;
        }

        $systemPrompt = $this->interpolate($data['system_prompt'] ?? 'You are a helpful assistant.', $variables) ?? '';
        $userPrompt = $this->interpolate($data['user_prompt'] ?? '{{last_message}}', $variables) ?? '';

        $reply = $this->callAiProvider($credential, $systemPrompt, $userPrompt, $data['model'] ?? null);
        if (! $reply) {
            return;
        }

        $responseVariable = $data['response_variable'] ?? null;
        if ($responseVariable) {
            $this->setVariable($session, $responseVariable, $reply);
        }

        $this->sendAndDecrement($channel, $account, $conversation, 'text', $reply, null, "bot:{$bot->id}");
    }

    /**
     * Anthropic's Messages API has a genuinely different shape (auth
     * header, request/response body) from everyone else here; every other
     * provider speaks the same OpenAI-compatible chat-completions shape,
     * just at a different base URL — so one generic branch covers 7
     * providers instead of writing 7 near-identical HTTP calls.
     */
    private function callAiProvider(WhatsappBotCredential $credential, string $systemPrompt, string $userPrompt, ?string $model): ?string
    {
        try {
            if ($credential->provider === 'anthropic') {
                $response = Http::withHeaders([
                    'x-api-key' => $credential->api_key,
                    'anthropic-version' => '2023-06-01',
                ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model ?: 'claude-3-5-haiku-latest',
                    'max_tokens' => 1024,
                    'system' => $systemPrompt,
                    'messages' => [['role' => 'user', 'content' => $userPrompt]],
                ]);

                return $response->successful() ? $response->json('content.0.text') : null;
            }

            $baseUrls = [
                'openai' => 'https://api.openai.com/v1',
                'groq' => 'https://api.groq.com/openai/v1',
                'deepseek' => 'https://api.deepseek.com/v1',
                'together' => 'https://api.together.xyz/v1',
                'openrouter' => 'https://openrouter.ai/api/v1',
                'mistral' => 'https://api.mistral.ai/v1',
                'perplexity' => 'https://api.perplexity.ai',
            ];
            $defaultModels = [
                'openai' => 'gpt-4o-mini',
                'groq' => 'llama-3.3-70b-versatile',
                'deepseek' => 'deepseek-chat',
                'together' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
                'openrouter' => 'openai/gpt-4o-mini',
                'mistral' => 'mistral-small-latest',
                'perplexity' => 'sonar',
            ];

            $baseUrl = $baseUrls[$credential->provider] ?? null;
            if (! $baseUrl) {
                return null;
            }

            $response = Http::withToken($credential->api_key)->timeout(20)->post("{$baseUrl}/chat/completions", [
                'model' => $model ?: $defaultModels[$credential->provider],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

            return $response->successful() ? $response->json('choices.0.message.content') : null;
        } catch (\Throwable $e) {
            Log::warning('Bot flow AI reply failed', ['provider' => $credential->provider, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function findNodeByType(array $nodes, string $type): ?array
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === $type) {
                return $node;
            }
        }

        return null;
    }

    private function nextNodeId(array $edges, string $fromId, ?string $sourceHandle = null): ?string
    {
        foreach ($edges as $edge) {
            if (($edge['source'] ?? null) !== $fromId) {
                continue;
            }

            if ($sourceHandle !== null && ($edge['sourceHandle'] ?? null) !== $sourceHandle) {
                continue;
            }

            return $edge['target'] ?? null;
        }

        return null;
    }

    private function evaluateCondition(mixed $actual, string $operator, mixed $expected): bool
    {
        $actual = (string) ($actual ?? '');
        $expected = (string) ($expected ?? '');

        return match ($operator) {
            'equals' => mb_strtolower($actual) === mb_strtolower($expected),
            'contains' => str_contains(mb_strtolower($actual), mb_strtolower($expected)),
            'gt' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            default => false,
        };
    }

    private function interpolate(?string $text, array $variables): ?string
    {
        if (! $text) {
            return $text;
        }

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            fn ($m) => (string) ($variables[$m[1]] ?? ''),
            $text,
        );
    }

    private function validateInput(string $fieldType, string $value): bool
    {
        return match ($fieldType) {
            'number' => is_numeric($value),
            'email' => (bool) filter_var($value, FILTER_VALIDATE_EMAIL),
            'phone' => preg_replace('/\D/', '', $value) !== '',
            default => true, // text/date/time/website — no format check in v1
        };
    }
}
