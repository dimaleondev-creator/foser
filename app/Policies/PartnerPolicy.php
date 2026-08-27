<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    public function viewAny(User $user): bool { return $user->can('content.view'); }
    public function view(User $user, Partner $partner): bool { return $user->can('content.view'); }
    public function create(User $user): bool { return $user->can('content.create'); }
    public function update(User $user, Partner $partner): bool { return $user->can('content.update'); }
    public function delete(User $user, Partner $partner): bool { return $user->can('content.delete'); }
}
