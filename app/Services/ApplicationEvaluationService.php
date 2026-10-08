<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicationEvaluationService
{
    public function assignEvaluator(string $applicationId, int $evaluatorId): object
    {
        $evaluator = User::query()->findOrFail($evaluatorId);
        $application = DB::table('applications')->where('id', $applicationId)->first();
        if (! $application) {
            throw ValidationException::withMessages(['application' => 'Dossier introuvable.']);
        }
        $assignableStatuses = ['university_review', 'evaluator_assignment', 'evaluation'];
        if (filled($application->workflow_status ?? null)) {
            abort_unless(in_array($application->workflow_status, $assignableStatuses, true), 422, 'Les évaluateurs ne peuvent être affectés qu’à un dossier en phase de revue ou d’évaluation.');
        } else {
            abort_unless(in_array($application->status, ['recevable', 'evaluation'], true), 422, 'Les évaluateurs ne peuvent être affectés qu’à un dossier en phase de revue ou d’évaluation.');
        }
        $this->assertEligibleEvaluator($evaluator, $application);
        if (DB::table('evaluations')->where('application_id', $applicationId)->where('evaluator_id', $evaluatorId)->exists()) {
            throw ValidationException::withMessages(['evaluator' => 'Cet évaluateur est déjà affecté à ce dossier.']);
        }

        $id = (string) Str::uuid();
        DB::table('evaluations')->insert(['id' => $id, 'application_id' => $applicationId, 'evaluator_id' => $evaluatorId, 'assigned_by' => Auth::id(), 'assigned_at' => now(), 'due_at' => now()->addDays(7), 'priority' => 'normal', 'status' => 'assigned', 'conflict_declared' => false, 'created_at' => now(), 'updated_at' => now()]);
        app(AuditLogger::class)->record('evaluation.assigned', 'evaluations', $id, [], ['application_id' => $applicationId, 'evaluator_id' => $evaluatorId]);
        app(NotificationService::class)->notify($evaluator, 'evaluation.assigned', ['application_id' => $applicationId, 'reference' => $application->reference], ['internal'], 'evaluation.assigned:'.$id);
        return DB::table('evaluations')->where('application_id', $applicationId)->where('evaluator_id', $evaluatorId)->first();
    }

    public function reassignEvaluator(string $evaluationId, int $newEvaluatorId): object
    {
        $assignment = DB::table('evaluations')->where('id', $evaluationId)->firstOrFail();
        abort_unless(in_array($assignment->status, ['assigned', 'in_progress', 'conflict'], true), 422, 'Une évaluation soumise ou validée ne peut pas être réattribuée.');
        $application = DB::table('applications')->where('id', $assignment->application_id)->firstOrFail();
        $evaluator = User::query()->findOrFail($newEvaluatorId);
        $this->assertEligibleEvaluator($evaluator, $application);
        if ($newEvaluatorId === (int) $assignment->evaluator_id) {
            throw ValidationException::withMessages(['evaluator_id' => 'Choisissez un autre évaluateur pour réattribuer le dossier.']);
        }
        if ($newEvaluatorId !== (int) $assignment->evaluator_id && DB::table('evaluations')->where('application_id', $assignment->application_id)->where('evaluator_id', $newEvaluatorId)->exists()) {
            throw ValidationException::withMessages(['evaluator_id' => 'Cet évaluateur est déjà affecté à ce dossier.']);
        }

        DB::transaction(function () use ($assignment, $evaluator): void {
            DB::table('evaluation_scores')->where('evaluation_id', $assignment->id)->delete();
            DB::table('evaluations')->where('id', $assignment->id)->update([
                'evaluator_id' => $evaluator->id, 'assigned_by' => Auth::id(), 'assigned_at' => now(),
                'due_at' => now()->addDays(7), 'status' => 'assigned', 'comment' => null,
                'conflict_declared' => false, 'conflict_reason' => null, 'submitted_at' => null,
                'completed_at' => null, 'updated_at' => now(),
            ]);
            app(AuditLogger::class)->record('evaluation.reassigned', 'evaluations', $assignment->id,
                ['evaluator_id' => $assignment->evaluator_id, 'status' => $assignment->status],
                ['evaluator_id' => $evaluator->id, 'application_id' => $assignment->application_id, 'performed_by' => Auth::id()]);
        });

        app(NotificationService::class)->notify($evaluator, 'evaluation.assigned', ['application_id' => $application->id, 'reference' => $application->reference], ['internal'], 'evaluation.reassigned:'.$assignment->id.':'.$evaluator->id);

        return DB::table('evaluations')->where('id', $assignment->id)->first();
    }

    public function startEvaluation(string $evaluationId, int $evaluatorId): void
    {
        DB::transaction(function () use ($evaluationId, $evaluatorId): void {
            $evaluation = DB::table('evaluations')->where('id', $evaluationId)->where('evaluator_id', $evaluatorId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($evaluation->status, ['assigned', 'in_progress', 'submitted', 'validated', 'conflict'], true), 404);
            if ($evaluation->status === 'assigned') {
                DB::table('evaluations')->where('id', $evaluationId)->update(['status' => 'in_progress', 'updated_at' => now()]);
                app(AuditLogger::class)->record('evaluation.started', 'evaluations', $evaluationId, ['status' => 'assigned'], ['status' => 'in_progress', 'evaluator_id' => $evaluatorId]);
            }
        });
    }

    public function submitEvaluation(string $evaluationId, int $evaluatorId, array $scores, ?string $comment = null, array $criterionComments = []): void
    {
        $evaluation = DB::table('evaluations')->where('id', $evaluationId)->where('evaluator_id', $evaluatorId)->first();
        if (! $evaluation) {
            throw ValidationException::withMessages(['evaluation' => 'Évaluation non autorisée.']);
        }
        if ($evaluation->status !== 'in_progress' || $evaluation->conflict_declared) {
            throw ValidationException::withMessages(['evaluation' => 'Cette évaluation est verrouillée ou concernée par un conflit.']);
        }
        $application = DB::table('applications')->where('id', $evaluation->application_id)->firstOrFail();
        $criteria = DB::table('evaluation_criteria')->where('program_id', $application->program_id)->get()->keyBy('id');
        if ($criteria->isEmpty() || $criteria->keys()->diff(array_map('strval', array_keys($scores)))->isNotEmpty()) {
            throw ValidationException::withMessages(['scores' => 'Une note est requise pour chaque critère du programme.']);
        }
        DB::transaction(function () use ($evaluation, $scores, $comment, $criterionComments, $criteria): void {
            foreach ($scores as $criterionId => $score) {
                $criterion = $criteria->get((string) $criterionId);
                if (! $criterion || ! is_numeric($score) || (float) $score < 0 || (float) $score > (float) $criterion->maximum_score) {
                    throw ValidationException::withMessages(['score' => 'Une note est invalide.']);
                }
                DB::table('evaluation_scores')->updateOrInsert(['evaluation_id' => $evaluation->id, 'criterion_id' => $criterionId], ['id' => (string) Str::uuid(), 'score' => $score, 'comment' => $criterionComments[$criterionId] ?? null, 'updated_at' => now(), 'created_at' => now()]);
            }
            DB::table('evaluations')->where('id', $evaluation->id)->update(['status' => 'submitted', 'comment' => $comment, 'submitted_at' => now(), 'completed_at' => now(), 'updated_at' => now()]);
            app(AuditLogger::class)->record('evaluation.submitted', 'evaluations', $evaluation->id, [], ['status' => 'submitted', 'comment' => $comment]);
        });
    }

    public function validateEvaluation(User $operator, string $evaluationId): void
    {
        abort_unless($operator->can('evaluations.validate'), 403);
        $evaluation = DB::table('evaluations')->where('id', $evaluationId)->firstOrFail();
        abort_unless($evaluation->status === 'submitted', 422, 'Seule une évaluation soumise peut être validée.');
        $application = DB::table('applications')->where('id', $evaluation->application_id)->firstOrFail();
        abort_unless(($application->workflow_status ?? null) === 'evaluation', 422, 'Le dossier n’est plus dans la phase de validation des évaluations.');
        DB::transaction(function () use ($operator, $evaluation): void {
            DB::table('evaluations')->where('id', $evaluation->id)->update(['status' => 'validated', 'validated_by' => $operator->id, 'validated_at' => now(), 'updated_at' => now()]);
            app(AuditLogger::class)->record('evaluation.validated', 'evaluations', $evaluation->id,
                ['status' => 'submitted'], ['status' => 'validated', 'validated_by' => $operator->id]);
        });
    }

    public function declareConflict(string $evaluationId, int $evaluatorId, ?string $reason = null): void
    {
        $evaluation = DB::table('evaluations')->where('id', $evaluationId)->where('evaluator_id', $evaluatorId)->firstOrFail();
        abort_unless(in_array($evaluation->status, ['assigned', 'in_progress'], true), 422);
        DB::table('evaluations')->where('id', $evaluationId)->update(['status' => 'conflict', 'conflict_declared' => true, 'conflict_reason' => $reason, 'updated_at' => now()]);
        app(AuditLogger::class)->record('evaluation.conflict_declared', 'evaluations', $evaluationId, ['status' => $evaluation->status], ['status' => 'conflict', 'reason' => $reason]);
    }

    public function publishResult(string $applicationId, string $decision, ?float $score, ?string $reason): void
    {
        $actor = User::query()->findOrFail(Auth::id());
        abort_unless($actor->can('applications.validate') || $actor->can('evaluations.validate'), 403);
        if (! in_array($decision, ['accepted', 'rejected', 'waitlisted'], true)) {
            throw ValidationException::withMessages(['decision' => 'Décision invalide.']);
        }
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless(in_array($application->status, ['evaluation', 'valide', 'decision'], true), 422);
        abort_unless(($application->workflow_status ?? null) === 'commission_review', 422, 'Le dossier doit être transmis à la commission avant publication du résultat.');
        abort_unless(! DB::table('evaluations')->where('application_id', $applicationId)->where('status', '!=', 'validated')->exists(), 422, 'Toutes les évaluations doivent être validées.');
        $resultId = (string) Str::uuid();
        DB::table('application_results')->updateOrInsert(['application_id' => $applicationId], ['id' => $resultId, 'decision' => $decision, 'score' => $score, 'reason' => $reason, 'published_at' => now(), 'decided_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('applications')->where('id', $applicationId)->update(['status' => 'decision', 'decided_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->where('id', $application->call_id)->update(['status' => 'closed', 'results_published_at' => now(), 'updated_at' => now()]);
        app(AuditLogger::class)->record('application.result_published', 'application_results', $resultId, [], ['application_id' => $applicationId, 'decision' => $decision]);
        $applicantId = $application->applicant_id;
        if ($applicantId) {
            app(NotificationService::class)->notify(User::findOrFail($applicantId), 'application.result', ['decision' => $decision], null, 'application.result:'.$applicationId);
        }
    }

    private function assertEligibleEvaluator(User $evaluator, object $application): void
    {
        abort_unless($evaluator->status === 'active', 422, 'Cet évaluateur n’est pas actif.');
        abort_unless($evaluator->account_type === 'evaluateur' && $evaluator->hasRole('evaluateur'), 403);
        if ((int) $application->applicant_id === (int) $evaluator->id || DB::table('applications')->where('call_id', $application->call_id)->where('applicant_id', $evaluator->id)->exists()) {
            throw ValidationException::withMessages(['evaluator' => 'Conflit d’intérêts : cet évaluateur est lié à ce dossier ou à cet appel.']);
        }
        $candidateUniversity = DB::table('student_profiles')->where('user_id', $application->applicant_id)->value('university_id');
        if ($candidateUniversity && DB::table('university_users')->where('user_id', $evaluator->id)->where('university_id', $candidateUniversity)->exists()) {
            throw ValidationException::withMessages(['evaluator' => 'Conflit d’intérêts : l’évaluateur appartient à l’établissement du candidat.']);
        }
        $expertise = $evaluator->evaluation_expertise ?? [];
        if ($expertise && filled($application->domain ?? null) && ! in_array(mb_strtolower(trim($application->domain)), array_map(static fn (string $item): string => mb_strtolower(trim($item)), $expertise), true)) {
            throw ValidationException::withMessages(['evaluator' => 'Le domaine d’expertise de cet évaluateur ne correspond pas au dossier.']);
        }
    }
}
