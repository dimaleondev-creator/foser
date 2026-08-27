<?php

namespace App\Policies;

use App\Models\User;

class SettingsPolicy
{
    public function manage(User $user): bool { return $user->can('settings.manage'); }
}
