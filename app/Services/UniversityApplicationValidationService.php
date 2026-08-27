<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UniversityApplicationValidationService
{
    public function validate(User $responsible, string $applicationId, string $universityId): object
    {
        $application = DB::table('applications')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')
            ->join('users', 'users.id', '=', 'applications.applicant_id')
            ->where('applications.id', $applicationId)
            ->where('student_profiles.university_id', $universityId)
            ->whereIn('applications.status', ['soumis', 'verification', 'incomplet', 'complement'])
            ->select('applications.*', 'student_profiles.university_id', 'users.name as student_name', 'users.email as student_email')
            ->first();
        abort_unless($application, 404);

        $timestamp = now();
        DB::transaction(function () use ($application, $responsible, $universityId, $timestamp): void {
            DB::table('applications')->where('id', $application->id)->update(['status' => 'eligible', 'updated_at' => $timestamp]);
            DB::table('application_status_histories')->insert([
                'id' => (string) Str::uuid(), 'application_id' => $application->id, 'changed_by' => $responsible->id,
                'from_status' => $application->status, 'to_status' => 'eligible',
                'reason' => 'Validation effectuée par le responsable universitaire.', 'changed_at' => $timestamp,
            ]);
            app(AuditLogger::class)->record('university.application.validated', 'applications', $application->id,
                ['status' => $application->status, 'university_id' => $universityId],
                ['status' => 'eligible', 'university_id' => $universityId, 'performed_by' => $responsible->id, 'performed_at' => $timestamp->toISOString()]);
        });

        $payload = ['application_id' => $application->id, 'reference' => $application->reference, 'university_id' => $universityId, 'status' => 'eligible'];
        app(NotificationService::class)->notify($responsible, 'university.application.validated', $payload, ['internal', 'email'], 'university-validation:'.$application->id.':'.$responsible->id);
        app(NotificationService::class)->notify(User::findOrFail($application->applicant_id), 'application.status_changed', $payload, ['internal', 'email'], 'application-status:'.$application->id.':eligible');
        User::query()->whereIn('account_type', ['admin', 'super_admin'])->where('status', 'active')->get()->each(fn (User $admin) => app(NotificationService::class)->notify($admin, 'university.application.validated', $payload, ['internal'], 'university-validation:'.$application->id.':admin:'.$admin->id));

        return $application;
    }
}
