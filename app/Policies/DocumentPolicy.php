<?php

namespace App\Policies;

use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool { return $user->can('documents.view'); }
    public function view(User $user, mixed $document): bool { return $user->can('documents.view'); }
    public function upload(User $user): bool { return $user->can('documents.upload'); }
    public function validate(User $user, mixed $document): bool { return $user->can('documents.validate'); }
    public function delete(User $user, mixed $document): bool { return $user->can('documents.delete'); }
}
