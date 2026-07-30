<?php

namespace App\Http\Controllers\Api\Whatsapp\PublicApi;

use App\Models\Account;
use App\Models\Channel;
use App\Models\WhatsappApiSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Shared by every Public REST API controller (screenshots 38-50) — these
 * routes sit outside auth:sanctum (external callers have no session), so
 * every model touched here is resolved explicitly via withoutGlobalScopes(),
 * same reasoning already established for FormPublicController/WebhookController.
 */
trait ResolvesPublicApiChannel
{
    /**
     * instance_id + access_token identify the channel — no session, matches
     * the reference app's own simple per-instance credential model. Works
     * whether the caller sent them as query params or a JSON body, since
     * Request::input() covers both transparently (same dual-mode examples
     * the reference screenshots themselves show).
     */
    private function resolveChannel(Request $request): Channel
    {
        $instanceId = $request->input('instance_id');
        $accessToken = $request->input('access_token');

        if (! $instanceId || ! $accessToken) {
            throw new HttpException(401, 'instance_id and access_token are required.');
        }

        $channel = Channel::withoutGlobalScopes()
            ->where('id', $instanceId)
            ->where('access_token', $accessToken)
            ->first();

        if (! $channel) {
            throw new HttpException(401, 'Invalid instance_id or access_token.');
        }

        return $channel;
    }

    /**
     * 403s if the channel's onboarding reseller has disabled this endpoint
     * group. No reseller (direct platform client, or the account is itself a
     * reseller/super_admin) or no settings row yet => unrestricted, so an
     * unaffiliated account is never silently blocked by a feature that
     * doesn't apply to it.
     */
    private function ensureGroupEnabled(Channel $channel, string $group): void
    {
        $account = Account::withoutGlobalScopes()->find($channel->account_id);
        $reseller = $account?->parent_account_id
            ? Account::withoutGlobalScopes()->find($account->parent_account_id)
            : null;

        if (! $reseller || $reseller->account_type !== Account::TYPE_RESELLER) {
            return;
        }

        $settings = WhatsappApiSettings::where('reseller_account_id', $reseller->id)->first();

        if ($settings && ! $settings->groupEnabled($group)) {
            throw new HttpException(403, "The \"{$group}\" API is not enabled for this account.");
        }
    }
}
