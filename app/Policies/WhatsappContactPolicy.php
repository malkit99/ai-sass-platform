<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappContact;

class WhatsappContactPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappContact $contact): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WhatsappContact $contact): bool
    {
        return true;
    }

    public function delete(User $user, WhatsappContact $contact): bool
    {
        return true;
    }
}
