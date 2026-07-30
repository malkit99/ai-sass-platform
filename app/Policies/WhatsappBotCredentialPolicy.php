<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappBotCredential;

class WhatsappBotCredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, WhatsappBotCredential $credential): bool
    {
        return true;
    }
}
