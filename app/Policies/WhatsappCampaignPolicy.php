<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappCampaign;

class WhatsappCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappCampaign $campaign): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WhatsappCampaign $campaign): bool
    {
        return true;
    }

    public function delete(User $user, WhatsappCampaign $campaign): bool
    {
        return $user->role === 'owner';
    }
}
