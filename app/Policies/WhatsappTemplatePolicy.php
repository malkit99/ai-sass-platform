<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappTemplate;

class WhatsappTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappTemplate $template): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WhatsappTemplate $template): bool
    {
        return true;
    }

    public function delete(User $user, WhatsappTemplate $template): bool
    {
        return $user->role === 'owner';
    }
}
