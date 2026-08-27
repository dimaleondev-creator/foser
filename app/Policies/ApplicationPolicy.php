<?php

namespace App\Policies;

use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user): bool { return $user->can('applications.view'); }
    public function view(User $user, mixed $application): bool { return $user->can('applications.view'); }
    public function create(User $user): bool { return $user->can('applications.create'); }
    public function update(User $user, mixed $application): bool { return $user->can('applications.update'); }
    public function submit(User $user, mixed $application): bool { return $user->can('applications.submit'); }
    public function validate(User $user, mixed $application): bool { return $user->can('applications.validate'); }
    public function reject(User $user, mixed $application): bool { return $user->can('applications.reject'); }
}
