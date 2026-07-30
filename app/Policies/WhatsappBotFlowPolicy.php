<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappBotFlow;

class WhatsappBotFlowPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappBotFlow $bot): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WhatsappBotFlow $bot): bool
    {
        return true;
    }

    public function delete(User $user, WhatsappBotFlow $bot): bool
    {
        return $user->role === 'owner';
    }
}
