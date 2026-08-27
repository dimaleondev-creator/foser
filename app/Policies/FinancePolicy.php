<?php

namespace App\Policies;

use App\Models\User;

class FinancePolicy
{
    public function view(User $user): bool { return $user->can('finance.view'); }
    public function manage(User $user): bool { return $user->can('finance.manage'); }
    public function export(User $user): bool { return $user->can('finance.export'); }
}
