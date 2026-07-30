<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappForm;
use App\Models\WhatsappFormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', WhatsappForm::class);

        return WhatsappForm::query()->withCount('submissions')->latest()->get();
    }

    /**
     * Dashboard stat cards (screenshot 91). `generated_revenue` is a real
     * aggregate over `whatsapp_forms.revenue` — no code path currently ever
     * writes a non-zero value there (no payment field type, no Commerce
     * module), so this legitimately reads 0 until that exists, same as the
     * CRM dashboard's "Unread" stat already being a known placeholder.
     */
    public function dashboard()
    {
        $account = Auth::user()->account;

        return response()->json([
            'active_forms' => WhatsappForm::where('status', WhatsappForm::STATUS_ACTIVE)->count(),
            'recent_leads' => WhatsappFormSubmission::where('account_id', $account->id)
                ->whereNotNull('lead_id')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'generated_revenue' => (float) WhatsappForm::sum('revenue'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', WhatsappForm::class);

        $data = $this->validated($request);
        $data['slug'] = $this->resolveSlug($request->input('slug'), $data['name']);

        return response()->json(WhatsappForm::create($data), 201);
    }

    public function update(Request $request, WhatsappForm $form)
    {
        $this->authorize('update', $form);

        $data = $this->validated($request);
        $data['slug'] = $this->resolveSlug($request->input('slug'), $data['name'], $form->id);

        $form->update($data);

        return $form;
    }

    public function destroy(WhatsappForm $form)
    {
        $this->authorize('delete', $form);

        $form->delete();

        return response()->noContent();
    }

    /**
     * Leads list (screenshot 96) — every submission for this form, newest
     * first, with the CRM lead relation loaded so the frontend can show the
     * "CRM LEAD" badge without a second round trip.
     */
    public function submissions(WhatsappForm $form)
    {
        $this->authorize('view', $form);

        return $form->submissions()->with('lead:id')->latest()->get();
    }

    /**
     * "Export PDF" in the reference screenshot — built as CSV instead, same
     * as every other export in this app (ContactController, GroupController)
     * rather than introducing this project's first PDF dependency for one
     * button.
     */
    public function exportSubmissions(WhatsappForm $form)
    {
        $this->authorize('view', $form);

        $inputFields = collect($form->fields)->reject(fn ($f) => in_array($f['type'], WhatsappForm::DISPLAY_ONLY_TYPES, true));

        return response()->streamDownload(function () use ($form, $inputFields) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'CRM Lead', ...$inputFields->pluck('label')->all(), 'IP Address', 'User Agent', 'Submitted At']);

            $form->submissions()->orderBy('id')->chunk(200, function ($submissions) use ($out, $inputFields) {
                foreach ($submissions as $submission) {
                    $row = [$submission->id, $submission->lead_id ? 'Yes' : 'No'];

                    foreach ($inputFields as $field) {
                        $value = $submission->data[$field['id']] ?? '';
                        $row[] = is_array($value) ? implode(', ', $value) : $value;
                    }

                    $row[] = $submission->ip_address;
                    $row[] = $submission->user_agent;
                    $row[] = $submission->created_at;

                    fputcsv($out, $row);
                }
            });

            fclose($out);
        }, "{$form->name}-submissions.csv");
    }

    /**
     * "Global Form Configuration" (screenshots 98-102). Only General/
     * WhatsApp/"Create Lead" are backed by real behavior (see
     * FormPublicController). `assign_to`/`ai_qualification`/`payment_enabled`/
     * `ivr_enabled` are stored but deliberately inert — no round-robin lead
     * assignment, AI integration, Commerce/payment, or IVR/voice-calling
     * system exists in this codebase yet. Validated permissively (not
     * locked to a single allowed value) so the UI can still show the full
     * tab set from the reference screenshots without the backend pretending
     * any of those specific sub-features actually do something.
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in([WhatsappForm::STATUS_DRAFT, WhatsappForm::STATUS_ACTIVE])],
            'channel_id' => ['nullable', 'integer', 'exists:channels,id'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.id' => ['required', 'string'],
            'fields.*.type' => ['required', Rule::in(WhatsappForm::FIELD_TYPES)],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.required' => ['boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['string', 'max:255'],
            'success_message' => ['nullable', 'string', 'max:1000'],
            'success_action' => ['required', Rule::in([WhatsappForm::SUCCESS_ACTION_MESSAGE, WhatsappForm::SUCCESS_ACTION_REDIRECT])],
            'success_redirect_url' => ['required_if:success_action,redirect', 'nullable', 'url', 'max:255'],
            'automation_config' => ['nullable', 'array'],
            'automation_config.recaptcha_enabled' => ['boolean'],
            'automation_config.admin_notify_enabled' => ['boolean'],
            'automation_config.admin_notify_phone' => ['nullable', 'string', 'max:20'],
            'automation_config.admin_notify_message' => ['nullable', 'string', 'max:4096'],
            'automation_config.user_reply_enabled' => ['boolean'],
            'automation_config.user_reply_message' => ['nullable', 'string', 'max:4096'],
            'automation_config.create_lead' => ['nullable', Rule::in(['instant', 'disabled'])],
            'automation_config.assign_to' => ['nullable', 'string', 'max:50'],
            'automation_config.ai_qualification' => ['nullable', 'string', 'max:50'],
            'automation_config.payment_enabled' => ['boolean'],
            'automation_config.ivr_enabled' => ['boolean'],
        ]);
    }

    private function resolveSlug(?string $requested, string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($requested ?: $name) ?: 'form';

        $exists = fn (string $slug) => WhatsappForm::withoutGlobalScopes()
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        $slug = $base;
        $suffix = 1;

        while ($exists($slug)) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
