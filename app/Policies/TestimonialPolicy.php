<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

class TestimonialPolicy
{
    public function viewAny(User $user): bool { return $user->can('content.view'); }
    public function view(User $user, Testimonial $testimonial): bool { return $user->can('content.view'); }
    public function create(User $user): bool { return $user->can('content.create'); }
    public function update(User $user, Testimonial $testimonial): bool { return $user->can('content.update'); }
    public function delete(User $user, Testimonial $testimonial): bool { return $user->can('content.delete'); }
}
