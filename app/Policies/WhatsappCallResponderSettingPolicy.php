<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsappCallResponderSetting;

class WhatsappCallResponderSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WhatsappCallResponderSetting $setting): bool
    {
        return true;
    }

    public function update(User $user, WhatsappCallResponderSetting $setting): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }
}
