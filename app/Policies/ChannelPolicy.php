<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

/**
 * Tenant scoping on Channel (BelongsToTenant) already restricts *which*
 * channels a query can return. Connecting/disconnecting a WhatsApp number is
 * destructive enough (kills an active session) to restrict to 'owner', same
 * as Lead deletion.
 */
class ChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Channel $channel): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === 'owner';
    }

    public function update(User $user, Channel $channel): bool
    {
        return $user->role === 'owner';
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $user->role === 'owner';
    }
}
