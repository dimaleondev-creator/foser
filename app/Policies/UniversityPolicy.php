<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UniversityPolicy
{
    public function view(User $user, Model $university): bool
    {
        return $this->belongsTo($user, $university->getKey());
    }

    public function update(User $user, Model $university): bool
    {
        return $user->can('university.manage') && $this->belongsTo($user, $university->getKey());
    }

    public function manageUsers(User $user): bool
    {
        return $user->can('university.manage');
    }

    public function export(User $user): bool
    {
        return $user->can('university.reports.view');
    }

    public function validateApplication(User $user, Model $university, Application $application): bool
    {
        return $this->canReviewApplication($user, $university, $application, 'university.applications.validate');
    }

    public function viewApplication(User $user, Model $university, Application $application): bool
    {
        return $this->canReviewApplication($user, $university, $application, 'university.applications.validate');
    }

    public function rejectApplication(User $user, Model $university, Application $application): bool
    {
        return $this->canReviewApplication($user, $university, $application, 'university.applications.reject');
    }

    public function requestApplicationCorrection(User $user, Model $university, Application $application): bool
    {
        return $this->canReviewApplication($user, $university, $application, 'university.applications.correction');
    }

    private function canReviewApplication(User $user, Model $university, Application $application, string $permission): bool
    {
        return $user->can($permission)
            && $this->belongsTo($user, (string) $university->getKey())
            && DB::table('student_profiles')
                ->where('user_id', $application->applicant_id)
                ->where('university_id', $university->getKey())
                ->exists();
    }

    private function belongsTo(User $user, string $universityId): bool
    {
        return (string) $user->university_id === $universityId
            || (string) \Illuminate\Support\Facades\DB::table('university_users')->where('user_id', $user->id)->where('university_id', $universityId)->value('university_id') === $universityId;
    }
}
