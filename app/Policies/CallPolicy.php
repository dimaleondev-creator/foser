<?php

namespace App\Policies;

use App\Models\User;

class CallPolicy
{
    public function viewAny(User $user): bool { return $user->can('calls.view'); }
    public function view(User $user, mixed $call): bool { return $user->can('calls.view'); }
    public function create(User $user): bool { return $user->can('calls.create'); }
    public function update(User $user, mixed $call): bool { return $user->can('calls.update'); }
    public function publish(User $user, mixed $call): bool { return $user->can('calls.publish'); }
    public function close(User $user, mixed $call): bool { return $user->can('calls.close'); }
}
