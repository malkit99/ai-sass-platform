<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\WhatsappApiSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reseller-controlled gating for the Public REST API's endpoint groups
 * (screenshots 38-50) — read by ApiPanel.vue to decide which sections to
 * show, edited only by the reseller's own owner user. Resolution mirrors
 * ResolvesPublicApiChannel::ensureGroupEnabled(), just starting from the
 * logged-in user's own account instead of a channel's.
 */
class WhatsappApiSettingsController extends Controller
{
    public function show(Request $request)
    {
        $account = $request->user()->rawAccount();
        $reseller = $this->resolveReseller($account);

        if (! $reseller) {
            return response()->json(['enabled_groups' => WhatsappApiSettings::ALL_GROUPS, 'editable' => false]);
        }

        $settings = WhatsappApiSettings::where('reseller_account_id', $reseller->id)->first();

        return response()->json([
            'enabled_groups' => $settings?->enabled_groups ?? WhatsappApiSettings::ALL_GROUPS,
            'editable' => $account->id === $reseller->id && $request->user()->isOwner(),
        ]);
    }

    public function update(Request $request)
    {
        $account = $request->user()->rawAccount();

        abort_if(
            ! $account || $account->account_type !== Account::TYPE_RESELLER || ! $request->user()->isOwner(),
            403, 'Only a reseller owner can edit the API settings.',
        );

        $data = $request->validate([
            'enabled_groups' => ['required', 'array'],
            'enabled_groups.*' => [Rule::in(WhatsappApiSettings::ALL_GROUPS)],
        ]);

        $settings = WhatsappApiSettings::updateOrCreate(
            ['reseller_account_id' => $account->id],
            ['enabled_groups' => $data['enabled_groups']],
        );

        return response()->json($settings);
    }

    private function resolveReseller(?Account $account): ?Account
    {
        if (! $account) {
            return null;
        }

        if ($account->account_type === Account::TYPE_RESELLER) {
            return $account;
        }

        if ($account->parent_account_id) {
            $parent = Account::withoutGlobalScopes()->find($account->parent_account_id);

            if ($parent && $parent->account_type === Account::TYPE_RESELLER) {
                return $parent;
            }
        }

        return null;
    }
}
