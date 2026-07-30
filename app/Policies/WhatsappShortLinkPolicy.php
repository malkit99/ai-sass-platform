<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappShortLink;

class WhatsappShortLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappShortLink $link): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, WhatsappShortLink $link): bool
    {
        return $user->role === 'owner';
    }
}
