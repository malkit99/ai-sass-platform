<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Jobs\Whatsapp\SendAutoReplyJob;
use App\Models\Account;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\MediaFile;
use App\Models\Pipeline;
use App\Models\WhatsappForm;
use App\Models\WhatsappFormSubmission;
use App\Rules\ValidMobileNumber;
use App\Services\Media\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Public, unauthenticated — a visitor filling out a published form has no
 * account/session at all. Every model touched here that normally relies on
 * BelongsToTenant's auth-driven scoping is instead explicitly scoped to
 * $form->account_id, since Auth::check() is always false in this context
 * and the global scope would otherwise silently no-op (matching a real
 * tenant, but not necessarily *this* form's tenant).
 *
 * "Global Form Configuration" (screenshots 98-102) coverage — what's real
 * vs. deliberately inert:
 *   - General (success message/redirect, WhatsApp account, form slug) — real.
 *   - WhatsApp tab (Admin Notification, User Auto-Reply) — real, both go
 *     through the form's own `channel_id`.
 *   - CRM & AI tab's "Create Lead" — real (reuses LeadController's own
 *     pipeline/stage-resolution pattern). "Assign To" (round-robin) and "AI
 *     Qualification" are stored but never acted on — no lead-assignment or
 *     AI-scoring system exists in this codebase yet.
 *   - Payment tab, IVR Call tab — stored but never acted on. No Commerce/
 *     payment module and no IVR/voice-calling system exist yet; the IVR tab
 *     is intentionally left inert per explicit instruction rather than
 *     half-building a voice feature.
 *   - "Enable reCAPTCHA" — stored but not verified; this project has no
 *     reCAPTCHA site/secret keys configured anywhere, so there's nothing to
 *     verify against yet.
 */
class FormPublicController extends Controller
{
    private const MAX_UPLOAD_KB = 10240; // 10MB — visitor attachments, not media-library assets

    public function show(string $slug)
    {
        $form = $this->findActiveForm($slug);

        return response()->json([
            'name' => $form->name,
            'fields' => $form->fields,
            'success_action' => $form->success_action,
            'success_message' => $form->success_message,
            'success_redirect_url' => $form->success_redirect_url,
        ]);
    }

    public function submit(Request $request, string $slug)
    {
        $form = $this->findActiveForm($slug);

        $data = $request->validate($this->buildRules($form));
        $values = $this->collectValues($request, $form, $data);

        $submission = WhatsappFormSubmission::create([
            'account_id' => $form->account_id,
            'form_id' => $form->id,
            'data' => $values,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $form->increment('submissions_count');

        $automation = $form->automation_config ?? [];

        if (($automation['create_lead'] ?? 'instant') === 'instant') {
            $lead = $this->createLead($form, $values);

            if ($lead) {
                $submission->update(['lead_id' => $lead->id]);
            }
        }

        $channel = $form->channel_id ? Channel::withoutGlobalScopes()->find($form->channel_id) : null;
        $channelUsable = $channel && $channel->account_id === $form->account_id && $channel->status === Channel::STATUS_CONNECTED;

        if ($channelUsable && ($automation['admin_notify_enabled'] ?? false) && ! empty($automation['admin_notify_phone'])) {
            $this->sendWhatsapp(
                $form, $channel, $automation['admin_notify_phone'],
                $this->renderTemplate($automation['admin_notify_message'] ?? '', $form, $values),
                "form_admin_notify:{$form->id}",
            );
        }

        if ($channelUsable && ($automation['user_reply_enabled'] ?? false)) {
            $submitterPhone = $this->firstValueOfType($form, $values, 'whatsapp');

            if ($submitterPhone) {
                $this->sendWhatsapp(
                    $form, $channel, $submitterPhone,
                    $this->renderTemplate($automation['user_reply_message'] ?? '', $form, $values),
                    "form_user_reply:{$form->id}",
                );
            }
        }

        return response()->json(['ok' => true], 201);
    }

    private function findActiveForm(string $slug): WhatsappForm
    {
        return WhatsappForm::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('status', WhatsappForm::STATUS_ACTIVE)
            ->firstOrFail();
    }

    private function buildRules(WhatsappForm $form): array
    {
        $rules = [];

        foreach ($form->fields as $field) {
            $type = $field['type'];

            if (in_array($type, WhatsappForm::DISPLAY_ONLY_TYPES, true)) {
                continue;
            }

            $key = $field['id'];
            $required = ($field['required'] ?? false) ? 'required' : 'nullable';

            $rules[$key] = match ($type) {
                'email' => [$required, 'email', 'max:255'],
                'whatsapp' => [$required, 'string', new ValidMobileNumber()],
                'number' => [$required, 'numeric'],
                'date' => [$required, 'date'],
                'time' => [$required, 'date_format:H:i'],
                'file' => [$required, 'file', 'max:'.self::MAX_UPLOAD_KB],
                'dropdown', 'radio' => [$required, 'string', Rule::in($field['options'] ?? [])],
                'checkboxes' => [$required, 'array'],
                default => [$required, 'string', 'max:4096'],
            };

            if ($type === 'checkboxes') {
                $rules["{$key}.*"] = ['string', Rule::in($field['options'] ?? [])];
            }
        }

        return $rules;
    }

    private function collectValues(Request $request, WhatsappForm $form, array $data): array
    {
        $values = [];

        foreach ($form->fields as $field) {
            $type = $field['type'];

            if (in_array($type, WhatsappForm::DISPLAY_ONLY_TYPES, true)) {
                continue;
            }

            $key = $field['id'];

            if ($type === 'file') {
                if ($request->hasFile($key)) {
                    $values[$key] = $this->storeUpload($form, $request->file($key));
                }
            } elseif (array_key_exists($key, $data)) {
                $values[$key] = $data[$key];
            }
        }

        return $values;
    }

    private function storeUpload(WhatsappForm $form, $file): string
    {
        $account = Account::withoutGlobalScopes()->find($form->account_id);
        $limitBytes = ($account?->plan?->limits['storage_limit_mb'] ?? 100) * 1024 * 1024;
        $usedBytes = MediaFile::storageUsedBytes($form->account_id);

        if ($usedBytes + $file->getSize() > $limitBytes) {
            throw ValidationException::withMessages([
                'file' => ['This form can\'t accept the attachment right now — please try a smaller file or contact the recipient directly.'],
            ]);
        }

        $disk = MediaStorage::disk('form_uploads');
        $path = MediaStorage::pathPrefix('form_uploads').'/'.date('Y/m').'/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        $disk->put($path, file_get_contents($file->getRealPath()));

        $media = MediaFile::create([
            'account_id' => $form->account_id,
            'purpose' => 'form_uploads',
            'disk' => MediaStorage::diskName(),
            'path' => $path,
            'url' => $disk->url($path),
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => MediaFile::typeFromMime($file->getMimeType()),
        ]);

        return $media->url;
    }

    /**
     * Name/phone/email are picked from the *first* field of the matching
     * type in the form's own schema — simple, predictable heuristic given
     * there's no explicit "map this field to the lead's name" UI. Always
     * uses the account's default pipeline — the CRM & AI tab only offers
     * "Create Lead: Instant/Disabled", no pipeline picker.
     */
    private function createLead(WhatsappForm $form, array $values): ?Lead
    {
        $pipeline = Pipeline::withoutGlobalScopes()->where('account_id', $form->account_id)->where('is_default', true)->first();

        if (! $pipeline) {
            Log::warning('Form submission could not create a lead — no pipeline available', ['form_id' => $form->id]);

            return null;
        }

        $stageId = $pipeline->stages()->orderBy('order')->value('id');

        return Lead::create([
            'account_id' => $form->account_id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stageId,
            'name' => $this->firstValueOfType($form, $values, 'text') ?? 'Form submission',
            'phone' => $this->firstValueOfType($form, $values, 'whatsapp'),
            'email' => $this->firstValueOfType($form, $values, 'email'),
            'source' => "form:{$form->id}",
            'last_activity_at' => now(),
        ]);
    }

    private function sendWhatsapp(WhatsappForm $form, Channel $channel, string $toPhone, ?string $message, string $sentBy): void
    {
        if (! $message) {
            return;
        }

        $conversation = Conversation::withoutGlobalScopes()->firstOrCreate(
            ['channel_id' => $channel->id, 'contact_phone' => $toPhone],
            ['account_id' => $form->account_id, 'last_message_at' => now()],
        );

        SendAutoReplyJob::dispatch($channel->id, $conversation->id, 'text', $message, null, null, $sentBy);
    }

    /**
     * {name} → first text-type field's value, {data} → a plain-text summary
     * of every submitted field (screenshot 99's "New form submission:
     * {data}" example) — the only two placeholders the reference UI shows.
     */
    private function renderTemplate(string $template, WhatsappForm $form, array $values): string
    {
        $name = $this->firstValueOfType($form, $values, 'text') ?? '';

        $summary = collect($form->fields)
            ->reject(fn ($f) => in_array($f['type'], WhatsappForm::DISPLAY_ONLY_TYPES, true))
            ->map(function ($field) use ($values) {
                $value = $values[$field['id']] ?? null;
                if ($value === null || $value === '') {
                    return null;
                }

                return $field['label'].': '.(is_array($value) ? implode(', ', $value) : $value);
            })
            ->filter()
            ->implode(', ');

        return str_replace(['{name}', '{data}'], [$name, $summary], $template);
    }

    private function firstValueOfType(WhatsappForm $form, array $values, string $type): ?string
    {
        foreach ($form->fieldsOfType($type) as $field) {
            if (! empty($values[$field['id']])) {
                return (string) $values[$field['id']];
            }
        }

        return null;
    }
}
