<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Tenant scoping on Lead (BelongsToTenant) already restricts *which* leads a
 * query can return. This policy only decides who may write to leads within
 * their own visible scope — deleting is restricted to 'owner' since it's
 * the most destructive action.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return true;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->role === 'owner';
    }
}
