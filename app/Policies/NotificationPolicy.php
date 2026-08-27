<?php

namespace App\Policies;

use App\Models\User;

class NotificationPolicy
{
    public function send(User $user): bool { return $user->can('notifications.send'); }
}
