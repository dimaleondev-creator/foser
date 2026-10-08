<?php

namespace App\Services;

use App\Enums\StudentApplicationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class StudentApplicationWorkflow
{
    private const TRANSITIONS = [
        'brouillon' => ['soumis'],
        'soumis' => ['verification'],
        'verification' => ['recevable', 'incomplet', 'complement', 'evaluation', 'rejete'],
        'recevable' => ['evaluation'],
        'incomplet' => ['complement'],
        'complement' => ['brouillon', 'soumis'],
        'evaluation' => ['valide', 'rejete'],
        'valide' => ['decision', 'approuve', 'rejete'],
        'decision' => ['approuve', 'rejete'],
        'approuve' => ['engage'],
        'engage' => ['decaisse'],
        'decaisse' => ['paye'],
        'paye' => ['archive'],
        'archive' => [],
        'rejete' => [],
        'cloture' => [],
    ];

    public function createDraft(User $student, ?string $callId = null): object
    {
        $profile = DB::table('student_profiles')->where('user_id', $student->id)->first();

        if (! $profile) {
            throw ValidationException::withMessages(['profile' => 'Complétez votre profil étudiant avant de créer un dossier.']);
        }
        if (blank($profile->inee) || in_array($profile->ine_status ?? 'pending', ['rejected', 'suspended'], true)) {
            throw ValidationException::withMessages(['inee' => 'Un INEE déclaré et non suspendu est obligatoire pour créer un dossier.']);
        }

        $call = $this->openCall($callId);
        $roles = array_values(array_filter((array) json_decode((string) ($call->eligibility_roles ?? '[]'), true)));
        if ($roles && ! $student->hasAnyRole($roles)) {
            throw ValidationException::withMessages(['eligibility' => 'Votre profil n’est pas éligible à cet appel.']);
        }
        if ($call->places && DB::table('applications')->where('call_id', $call->id)->whereIn('status', ['soumis', 'verification', 'evaluation', 'valide', 'approuve', 'engage', 'decaisse', 'submitted', 'under_review', 'eligible', 'evaluated'])->count() >= $call->places) {
            throw ValidationException::withMessages(['places' => 'Le nombre de places disponibles est atteint.']);
        }

        $existing = DB::table('applications')->where('call_id', $call->id)->where('applicant_id', $student->id)->where('status', 'brouillon')->first();
        if ($existing) {
            return $existing;
        }

        $id = (string) Str::uuid();
        DB::table('applications')->insert([
            'id' => $id,
            'call_id' => $call->id,
            'program_id' => $call->program_id,
            'applicant_id' => $student->id,
            'reference' => 'FOSER-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'status' => StudentApplicationStatus::BROUILLON->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->log($id, $student->id, null, StudentApplicationStatus::BROUILLON->value);
        app(AuditLogger::class)->record('application.created', 'applications', $id, [], ['call_id' => $call->id, 'applicant_id' => $student->id]);

        return DB::table('applications')->where('id', $id)->first();
    }

    public function updateDraft(User $student, string $applicationId, array $data): object
    {
        $application = DB::table('applications')->where('id', $applicationId)->where('applicant_id', $student->id)->first();
        abort_unless($application, 404);
        abort_unless(in_array($application->status, ['brouillon', 'complement'], true), 403);

        DB::table('applications')->where('id', $applicationId)->update([...$data, 'updated_at' => now()]);
        app(AuditLogger::class)->record('application.draft_updated', 'applications', $applicationId, (array) $application, $data);

        return DB::table('applications')->where('id', $applicationId)->first();
    }

    public function deleteDraft(User $student, string $applicationId): void
    {
        $application = DB::table('applications')->where('id', $applicationId)->where('applicant_id', $student->id)->first();
        abort_unless($application, 404);
        abort_unless($application->status === StudentApplicationStatus::BROUILLON->value, 403);
        DB::table('applications')->where('id', $applicationId)->update(['deleted_at' => now(), 'updated_at' => now()]);
        app(AuditLogger::class)->record('application.draft_deleted', 'applications', $applicationId, (array) $application, []);
    }

    public function transition(User $student, string $applicationId, StudentApplicationStatus $next): void
    {
        $application = DB::table('applications')->where('id', $applicationId)->where('applicant_id', $student->id)->first();

        if (! $application) {
            abort(404);
        }

        if (! in_array($next->value, self::TRANSITIONS[$application->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'Cette transition de dossier n’est pas autorisée.']);
        }

        if ($next !== StudentApplicationStatus::BROUILLON && ! in_array($application->status, ['brouillon', 'complement', 'incomplet'], true)) {
            throw ValidationException::withMessages(['status' => 'Un dossier déjà soumis ne peut pas être modifié par un étudiant.']);
        }

        if ($next === StudentApplicationStatus::SOUMIS) {
            DB::transaction(function () use ($application, $student, $applicationId, $next): void {
                app(CallCapacityService::class)->lockAndAssertAvailable($application->call_id, $applicationId);
                $completeness = app(ApplicationCompletenessService::class)->check($applicationId);
                if (! $completeness['is_complete']) {
                    throw ValidationException::withMessages([
                        'application' => 'Le dossier est incomplet.',
                        'missing_fields' => implode(', ', $completeness['missing_fields']),
                        'missing_documents' => implode(', ', $completeness['missing_documents']),
                    ]);
                }

                $this->applyTransition($application, $student, $next);
                $submitted = (object) [...(array) $application, 'status' => StudentApplicationStatus::SOUMIS->value];
                $this->applyTransition($submitted, $student, StudentApplicationStatus::VERIFICATION);
                $missing = $this->missingDocuments($application->id, $application->program_id);
                $this->applyTransition(
                    (object) [...(array) $application, 'status' => StudentApplicationStatus::VERIFICATION->value],
                    $student,
                    $missing ? StudentApplicationStatus::INCOMPLET : StudentApplicationStatus::RECEVABLE,
                    $missing ? implode(', ', $missing) : null,
                );
            });

            return;
        }

        $this->applyTransition($application, $student, $next);
    }

    public function checkCompleteness(User $operator, string $applicationId): StudentApplicationStatus
    {
        abort_unless($operator->can('applications.update') || $operator->can('applications.validate'), 403);
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless($application->status === StudentApplicationStatus::VERIFICATION->value, 422);
        $missing = $this->missingDocuments($application->id, $application->program_id);
        $next = $missing ? StudentApplicationStatus::INCOMPLET : StudentApplicationStatus::RECEVABLE;
        $this->applyTransition($application, $operator, $next, $missing ? implode(', ', $missing) : null);

        return $next;
    }

    public function transitionByOperator(User $operator, string $applicationId, StudentApplicationStatus $next, ?string $reason = null): void
    {
        abort_unless($operator->can('applications.update') || $operator->can('applications.validate'), 403);
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless(in_array($next->value, self::TRANSITIONS[$application->status] ?? [], true), 422);
        $this->applyTransition($application, $operator, $next, $reason);
    }

    public function missingDocuments(string $applicationId, string $programId): array
    {
        $required = DB::table('required_documents')->where('program_id', $programId)->where('is_required', true)->pluck('document_type');
        $provided = DB::table('application_documents')
            ->join('documents', 'documents.id', '=', 'application_documents.document_id')
            ->where('application_id', $applicationId)
            ->where('application_documents.status', '!=', 'rejected')
            ->pluck('documents.document_type');
        return $required->diff($provided)->values()->all();
    }

    private function applyTransition(object $application, User $actor, StudentApplicationStatus $next, ?string $reason = null): void
    {
        DB::transaction(function () use ($application, $actor, $next, $reason): void {
            DB::table('applications')->where('id', $application->id)->update(['status' => $next->value, 'submitted_at' => $next === StudentApplicationStatus::SOUMIS ? now() : $application->submitted_at, 'updated_at' => now()]);
            DB::table('application_status_histories')->insert(['id' => (string) Str::uuid(), 'application_id' => $application->id, 'changed_by' => $actor->id, 'from_status' => $application->status, 'to_status' => $next->value, 'reason' => $reason, 'changed_at' => now()]);
            app(AuditLogger::class)->record('application.status_changed', 'applications', $application->id, ['status' => $application->status], ['status' => $next->value, 'reason' => $reason]);
        });

        try {
            app(NotificationService::class)->notify(User::findOrFail($application->applicant_id), 'application.status_changed', ['status' => $next->label()], ['internal', 'email'], 'application.status_changed:'.$application->id.':'.$next->value);
        } catch (TransportExceptionInterface) {
        }
    }

    private function log(string $applicationId, int $userId, ?string $from, string $to): void
    {
        DB::table('application_status_histories')->insert([
            'id' => (string) Str::uuid(),
            'application_id' => $applicationId,
            'changed_by' => $userId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => null,
            'changed_at' => now(),
        ]);
    }

    private function openCall(?string $callId): object
    {
        $query = DB::table('calls')->where('status', 'published')->whereDate('opens_at', '<=', today())->whereDate('closes_at', '>=', today());
        $call = $callId ? $query->where('id', $callId)->first() : $query->latest('closes_at')->first();
        if (! $call) {
            throw ValidationException::withMessages(['call' => 'Aucun appel à candidatures ouvert.']);
        }
        return $call;
    }
}
