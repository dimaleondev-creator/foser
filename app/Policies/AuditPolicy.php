<?php

namespace App\Policies;

use App\Models\User;

class AuditPolicy
{
    public function view(User $user): bool { return $user->can('audit.view'); }
}
