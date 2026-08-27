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
        abort_unless($evaluator->status === 'active', 422, 'Cet évaluateur n’est pas actif.');
        abort_unless($evaluator->hasRole('evaluateur') || $evaluator->can('evaluations.view'), 403);
        $application = DB::table('applications')->where('id', $applicationId)->first();
        if (! $application) {
            throw ValidationException::withMessages(['application' => 'Dossier introuvable.']);
        }
        if (DB::table('evaluations')->where('application_id', $applicationId)->where('evaluator_id', $evaluatorId)->exists()) {
            throw ValidationException::withMessages(['evaluator' => 'Cet évaluateur est déjà affecté à ce dossier.']);
        }
        if ((int) $application->applicant_id === $evaluatorId || DB::table('applications')->where('call_id', $application->call_id)->where('applicant_id', $evaluatorId)->exists()) {
            throw ValidationException::withMessages(['evaluator' => 'Conflit d’intérêts : cet évaluateur est lié à ce dossier ou à cet appel.']);
        }
        $candidateUniversity = DB::table('student_profiles')->where('user_id', $application->applicant_id)->value('university_id');
        if ($candidateUniversity && DB::table('university_users')->where('user_id', $evaluatorId)->where('university_id', $candidateUniversity)->exists()) {
            throw ValidationException::withMessages(['evaluator' => 'Conflit d’intérêts : l’évaluateur appartient à l’établissement du candidat.']);
        }

        $id = (string) Str::uuid();
        DB::table('evaluations')->insert(['id' => $id, 'application_id' => $applicationId, 'evaluator_id' => $evaluatorId, 'assigned_at' => now(), 'due_at' => now()->addDays(7), 'priority' => 'normal', 'status' => 'assigned', 'conflict_declared' => false, 'created_at' => now(), 'updated_at' => now()]);
        app(AuditLogger::class)->record('evaluation.assigned', 'evaluations', $id, [], ['application_id' => $applicationId, 'evaluator_id' => $evaluatorId]);
        return DB::table('evaluations')->where('application_id', $applicationId)->where('evaluator_id', $evaluatorId)->first();
    }

    public function submitEvaluation(string $evaluationId, int $evaluatorId, array $scores, ?string $comment = null): void
    {
        $evaluation = DB::table('evaluations')->where('id', $evaluationId)->where('evaluator_id', $evaluatorId)->first();
        if (! $evaluation) {
            throw ValidationException::withMessages(['evaluation' => 'Évaluation non autorisée.']);
        }
        if ($evaluation->status === 'submitted' || $evaluation->conflict_declared) {
            throw ValidationException::withMessages(['evaluation' => 'Cette évaluation est verrouillée ou concernée par un conflit.']);
        }
        DB::transaction(function () use ($evaluation, $scores, $comment): void {
            foreach ($scores as $criterionId => $score) {
                $criterion = DB::table('evaluation_criteria')->where('id', $criterionId)->first();
                if (! $criterion || ! is_numeric($score) || (float) $score < 0 || (float) $score > (float) $criterion->maximum_score) {
                    throw ValidationException::withMessages(['score' => 'Une note est invalide.']);
                }
                DB::table('evaluation_scores')->updateOrInsert(['evaluation_id' => $evaluation->id, 'criterion_id' => $criterionId], ['id' => (string) Str::uuid(), 'score' => $score, 'updated_at' => now(), 'created_at' => now()]);
            }
            DB::table('evaluations')->where('id', $evaluation->id)->update(['status' => 'submitted', 'comment' => $comment, 'submitted_at' => now(), 'updated_at' => now()]);
            app(AuditLogger::class)->record('evaluation.submitted', 'evaluations', $evaluation->id, [], ['status' => 'submitted', 'comment' => $comment]);
        });
    }

    public function declareConflict(string $evaluationId, int $evaluatorId, ?string $reason = null): void
    {
        $evaluation = DB::table('evaluations')->where('id', $evaluationId)->where('evaluator_id', $evaluatorId)->firstOrFail();
        abort_unless($evaluation->status !== 'submitted', 422);
        DB::table('evaluations')->where('id', $evaluationId)->update(['status' => 'conflict', 'conflict_declared' => true, 'comment' => $reason, 'updated_at' => now()]);
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
        abort_unless(! DB::table('evaluations')->where('application_id', $applicationId)->where('status', '!=', 'submitted')->exists(), 422, 'Toutes les évaluations doivent être soumises.');
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
}
