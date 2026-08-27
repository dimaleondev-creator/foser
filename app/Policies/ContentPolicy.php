<?php

namespace App\Policies;

use App\Models\User;

class ContentPolicy
{
    public function viewAny(User $user): bool { return $user->can('content.view'); }
    public function view(User $user, mixed $content): bool { return $user->can('content.view'); }
    public function create(User $user): bool { return $user->can('content.create'); }
    public function update(User $user, mixed $content): bool { return $user->can('content.update'); }
    public function publish(User $user, mixed $content): bool { return $user->can('content.publish'); }
    public function delete(User $user, mixed $content): bool { return $user->can('content.delete'); }
}
