<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappGroup;

class WhatsappGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappGroup $group): bool
    {
        return true;
    }
}
