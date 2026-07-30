<?php

namespace App\Jobs\Whatsapp;

use App\Models\Conversation;
use App\Models\WhatsappBotFlow;
use App\Models\WhatsappBotSession;
use App\Services\Whatsapp\BotFlowExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs BotFlowExecutor on the queue instead of inline inside
 * WebhookController — same reason SendAutoReplyJob/RejectCallJob already
 * are (see their own docblocks): a flow can send several messages in one
 * burst, each a bridge round-trip, and the bridge's inbound-message webhook
 * POST has its own 10s delivery timeout.
 */
class RunBotFlowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MODE_START = 'start';

    public const MODE_RESUME = 'resume';

    public const MODE_CONTINUE = 'continue';

    public function __construct(
        private readonly int $conversationId,
        private readonly string $mode,
        private readonly ?int $botFlowId = null,
        private readonly ?string $inboundBody = null,
        private readonly ?string $continueNodeId = null,
    ) {}

    public function handle(BotFlowExecutor $executor): void
    {
        $conversation = Conversation::withoutGlobalScopes()->find($this->conversationId);
        if (! $conversation) {
            return;
        }

        if ($this->mode === self::MODE_START) {
            $bot = WhatsappBotFlow::withoutGlobalScopes()->find($this->botFlowId);
            if ($bot) {
                $executor->start($bot, $conversation);
            }

            return;
        }

        $session = WhatsappBotSession::where('conversation_id', $conversation->id)
            ->where('status', WhatsappBotSession::STATUS_ACTIVE)
            ->first();

        if (! $session) {
            return;
        }

        match ($this->mode) {
            self::MODE_RESUME => $executor->resume($session, $this->inboundBody ?? ''),
            self::MODE_CONTINUE => $executor->continueFrom($session, $this->continueNodeId ?? ''),
            default => null,
        };
    }
}
