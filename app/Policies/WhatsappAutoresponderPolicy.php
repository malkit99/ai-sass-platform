<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappAutoresponder;

class WhatsappAutoresponderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappAutoresponder $autoresponder): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WhatsappAutoresponder $autoresponder): bool
    {
        return true;
    }

    public function delete(User $user, WhatsappAutoresponder $autoresponder): bool
    {
        return $user->role === 'owner';
    }
}
