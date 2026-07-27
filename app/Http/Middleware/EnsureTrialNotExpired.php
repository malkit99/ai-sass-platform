<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks authenticated requests once an account's trial has expired.
 * See .claude/build-plan/06-roadmap.md Phase 0 — "plan/trial model".
 */
class EnsureTrialNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $account = $user->rawAccount();

            if ($account && $account->trial_expires_at?->isPast() && $account->status === 'active') {
                return response()->json([
                    'message' => 'Your trial has expired. Please upgrade your plan to continue.',
                ], 402);
            }
        }

        return $next($request);
    }
}
