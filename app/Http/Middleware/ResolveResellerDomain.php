<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\ResellerDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the incoming request's Host header to a reseller account (if any),
 * so white-label branding can be applied before the user even logs in.
 * See .claude/build-plan/07-reseller-model.md.
 *
 * Deliberately bypasses Account's tenant scope: this must resolve the same
 * reseller regardless of who (if anyone) is currently authenticated — e.g. a
 * logged-in client's own tenant subtree doesn't include its parent reseller,
 * which would otherwise hide the very reseller this domain points to.
 */
class ResolveResellerDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = ResellerDomain::where('domain', $request->getHost())->first();

        if ($domain) {
            $request->attributes->set(
                'reseller_account',
                Account::withoutGlobalScopes()->find($domain->reseller_account_id),
            );
        }

        return $next($request);
    }
}
