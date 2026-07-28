<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappChatbotRule;

class WhatsappChatbotRulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappChatbotRule $rule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WhatsappChatbotRule $rule): bool
    {
        return true;
    }

    public function delete(User $user, WhatsappChatbotRule $rule): bool
    {
        return $user->role === 'owner';
    }
}
