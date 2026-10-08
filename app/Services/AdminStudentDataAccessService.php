<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AdminStudentDataAccessService
{
    public function sensitiveIdentifiers(User $student): array
    {
        Gate::authorize('students.view_sensitive_data');
        abort_unless($student->account_type === 'etudiant', 404);

        $profile = $student->studentProfile;
        abort_unless($profile, 404);

        $audit = app(AuditLogger::class);
        foreach (['national_id', 'nip'] as $field) {
            $audit->record('students.'.$field.'.viewed', 'student_profiles', (string) $profile->id, [], ['field' => $field]);
        }

        return ['national_id' => $profile->national_id, 'nip' => $profile->nip];
    }

    public function parentInformation(User $student): array
    {
        Gate::authorize('students.view_parent_information');
        abort_unless($student->account_type === 'etudiant', 404);

        $profile = $student->studentProfile;
        abort_unless($profile, 404);
        app(AuditLogger::class)->record('students.parent_information.viewed', 'student_profiles', (string) $profile->id, [], ['fields' => ['father', 'mother']]);

        return $profile->only([
            'father_first_name', 'father_last_name', 'father_residence_country', 'father_function',
            'mother_first_name', 'mother_last_name', 'mother_residence_country', 'mother_function',
        ]);
    }
}