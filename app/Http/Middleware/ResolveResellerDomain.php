<?php

namespace App\Http\Middleware;

use App\Models\ResellerDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the incoming request's Host header to a reseller account (if any),
 * so white-label branding can be applied before the user even logs in.
 * See .claude/build-plan/07-reseller-model.md.
 */
class ResolveResellerDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = ResellerDomain::where('domain', $request->getHost())->first();

        if ($domain) {
            $request->attributes->set('reseller_account', $domain->resellerAccount);
        }

        return $next($request);
    }
}
