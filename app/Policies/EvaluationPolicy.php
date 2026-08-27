<?php

namespace App\Policies;

use App\Models\User;

class EvaluationPolicy
{
    public function viewAny(User $user): bool { return $user->can('evaluations.view'); }
    public function view(User $user, mixed $evaluation): bool { return $user->can('evaluations.view'); }
    public function create(User $user): bool { return $user->can('evaluations.create'); }
    public function update(User $user, mixed $evaluation): bool { return $user->can('evaluations.update'); }
    public function validate(User $user, mixed $evaluation): bool { return $user->can('evaluations.validate'); }
}
