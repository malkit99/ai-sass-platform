<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Tenant scoping on ActivityLog (BelongsToTenant) already restricts *which*
 * entries a query can return — any authenticated user in the tenant can view
 * their account's own audit trail.
 */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
}
