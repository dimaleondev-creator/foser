<?php

namespace App\Policies;

use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool { return $user->can('programs.view'); }
    public function view(User $user, mixed $program): bool { return $user->can('programs.view'); }
    public function create(User $user): bool { return $user->can('programs.create'); }
    public function update(User $user, mixed $program): bool { return $user->can('programs.update'); }
    public function delete(User $user, mixed $program): bool { return $user->can('programs.delete'); }
}
