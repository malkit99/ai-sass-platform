<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappForm;

class WhatsappFormPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappForm $form): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WhatsappForm $form): bool
    {
        return true;
    }

    public function delete(User $user, WhatsappForm $form): bool
    {
        return $user->role === 'owner';
    }
}
