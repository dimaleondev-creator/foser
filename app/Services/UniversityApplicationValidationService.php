<?php

namespace App\Services;

use App\Models\User;
use App\Models\Application;
use App\Models\University;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        Gate::authorize('validateApplication', [University::findOrFail($universityId), Application::findOrFail($application->id)]);

        $completeness = app(ApplicationCompletenessService::class)->check($applicationId);
        if (! $completeness['is_complete']) {
            throw ValidationException::withMessages([
                'application' => 'Le dossier est incomplet et ne peut pas être validé.',
                'missing_fields' => implode(', ', $completeness['missing_fields']),
                'missing_documents' => implode(', ', $completeness['missing_documents']),
            ]);
        }

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

    public function reject(User $responsible, string $applicationId, string $universityId, string $reason): object
    {
        return $this->transition($responsible, $applicationId, $universityId, 'rejectApplication', 'rejected', $reason, 'university.application.rejected');
    }

    public function requestCorrection(User $responsible, string $applicationId, string $universityId, string $reason): object
    {
        return $this->transition($responsible, $applicationId, $universityId, 'requestApplicationCorrection', 'complement', $reason, 'university.application.correction_requested');
    }

    private function transition(User $responsible, string $applicationId, string $universityId, string $ability, string $nextStatus, string $reason, string $event): object
    {
        $application = DB::table('applications')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')
            ->where('applications.id', $applicationId)
            ->where('student_profiles.university_id', $universityId)
            ->whereIn('applications.status', ['soumis', 'verification', 'incomplet', 'complement'])
            ->select('applications.*')
            ->firstOrFail();
        Gate::authorize($ability, [University::findOrFail($universityId), Application::findOrFail($applicationId)]);

        $timestamp = now();
        DB::transaction(function () use ($application, $responsible, $universityId, $nextStatus, $reason, $event, $timestamp): void {
            DB::table('applications')->where('id', $application->id)->update(['status' => $nextStatus, 'updated_at' => $timestamp]);
            DB::table('application_status_histories')->insert([
                'id' => (string) Str::uuid(), 'application_id' => $application->id, 'changed_by' => $responsible->id,
                'from_status' => $application->status, 'to_status' => $nextStatus,
                'reason' => $reason, 'changed_at' => $timestamp,
            ]);
            app(AuditLogger::class)->record($event, 'applications', $application->id,
                ['status' => $application->status, 'university_id' => $universityId],
                ['status' => $nextStatus, 'university_id' => $universityId, 'reason' => $reason, 'performed_by' => $responsible->id, 'performed_at' => $timestamp->toISOString()]);
        });

        app(NotificationService::class)->notify(User::findOrFail($application->applicant_id), 'application.status_changed', [
            'application_id' => $application->id, 'reference' => $application->reference, 'status' => $nextStatus, 'reason' => $reason,
        ], ['internal', 'email'], 'university-application:'.$application->id.':'.$nextStatus);

        return $application;
    }
}
