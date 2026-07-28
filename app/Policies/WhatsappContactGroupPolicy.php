<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappContactGroup;

class WhatsappContactGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappContactGroup $group): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WhatsappContactGroup $group): bool
    {
        return true;
    }

    public function delete(User $user, WhatsappContactGroup $group): bool
    {
        return true;
    }
}
