<?php

namespace App\Models\Concerns;

use App\Models\Account;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * For domain models owned by a tenant account (leads, messages, tickets, etc. — see
 * .claude/build-plan/03-architecture.md). Auto-scopes queries to the authenticated
 * user's account subtree and stamps new records with their account_id.
 *
 * Requires the model's table to have an `account_id` column.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function ($builder) {
            $user = Auth::user();

            if (! $user || ! $user->account) {
                return;
            }

            if ($user->account->account_type !== Account::TYPE_SUPER_ADMIN) {
                $builder->whereIn('account_id', $user->account->subtreeIds());
            }
        });

        static::creating(function ($model) {
            if (! $model->account_id && Auth::check()) {
                $model->account_id = Auth::user()->account_id;
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
