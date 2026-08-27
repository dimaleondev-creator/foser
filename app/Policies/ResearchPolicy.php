<?php

namespace App\Policies;

use App\Models\User;

class ResearchPolicy
{
    public function view(User $user): bool { return $user->can('research.view'); }
    public function manage(User $user): bool { return $user->can('research.manage'); }
    public function evaluate(User $user): bool { return $user->can('research.evaluate'); }
}
