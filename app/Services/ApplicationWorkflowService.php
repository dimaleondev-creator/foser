<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicationWorkflowService
{
    public function __construct(private ApplicationCompletenessService $completeness) {}

    public function submitApplication(User $student, string $applicationId): void
    {
        DB::transaction(function () use ($student, $applicationId): void {
            $application = DB::table('applications')->where('id', $applicationId)->where('applicant_id', $student->id)->lockForUpdate()->firstOrFail();
            abort_unless($application->workflow_status === ApplicationStatus::DRAFT->value, 422);
            app(CallCapacityService::class)->lockAndAssertAvailable($application->call_id, $applicationId);
            $result = $this->completeness->check($applicationId);
            if (! $result['is_complete']) {
                throw ValidationException::withMessages(['application' => 'Le dossier est incomplet.', 'missing_fields' => implode(', ', $result['missing_fields']), 'missing_documents' => implode(', ', $result['missing_documents'])]);
            }
            $this->transition($application, $student, ApplicationStatus::SUBMITTED, ['submitted_by' => $student->id, 'submitted_at' => now()]);
        });
    }

    public function verifyCompleteness(User $operator, string $applicationId): void
    {
        $this->authorize($operator, 'applications.verify', 'applications.validate');
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless($application->workflow_status === ApplicationStatus::SUBMITTED->value, 422);
        $result = $this->completeness->check($applicationId);
        $next = $result['is_complete'] ? ApplicationStatus::UNIVERSITY_REVIEW : ApplicationStatus::DOCUMENTS_PENDING;
        $this->transition($application, $operator, $next, ['verified_by' => $operator->id, 'verified_at' => now(), 'verification_reason' => $result['is_complete'] ? null : implode(', ', [...$result['missing_fields'], ...$result['missing_documents']])]);
    }

    public function approveUniversityReview(User $operator, string $applicationId): void
    {
        $this->authorize($operator, 'applications.verify', 'applications.validate');
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless($application->workflow_status === ApplicationStatus::UNIVERSITY_REVIEW->value, 422);
        $this->transition($application, $operator, ApplicationStatus::EVALUATION);
    }

    public function rejectUniversityReview(User $operator, string $applicationId, string $reason): void
    {
        $this->authorize($operator, 'applications.verify', 'applications.reject');
        abort_unless(filled($reason), 422);
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless($application->workflow_status === ApplicationStatus::UNIVERSITY_REVIEW->value, 422);
        $this->transition($application, $operator, ApplicationStatus::REJECTED, ['verification_reason' => $reason]);
    }

    public function assignEvaluators(User $operator, string $applicationId, array $evaluatorIds): void
    {
        $this->authorize($operator, 'applications.assign_evaluator', 'applications.update');
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless(in_array($application->workflow_status, [ApplicationStatus::EVALUATION->value, ApplicationStatus::UNIVERSITY_REVIEW->value], true), 422);
        DB::transaction(function () use ($operator, $application, $evaluatorIds): void {
            foreach (array_unique(array_map('intval', $evaluatorIds)) as $evaluatorId) {
                app(ApplicationEvaluationService::class)->assignEvaluator($application->id, $evaluatorId);
                DB::table('evaluations')->where('application_id', $application->id)->where('evaluator_id', $evaluatorId)->update(['assigned_by' => $operator->id, 'assigned_at' => now()]);
            }
            if ($application->workflow_status === ApplicationStatus::UNIVERSITY_REVIEW->value) {
                $this->transition($application, $operator, ApplicationStatus::EVALUATION);
            }
        });
    }

    public function sendToCommission(User $operator, string $applicationId): void
    {
        $this->authorize($operator, 'applications.review_commission', 'evaluations.validate');
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless($application->workflow_status === ApplicationStatus::EVALUATION->value, 422);
        abort_unless(DB::table('evaluations')->where('application_id', $applicationId)->exists(), 422);
        abort_unless(! DB::table('evaluations')->where('application_id', $applicationId)->where('status', '!=', 'validated')->exists(), 422, 'Toutes les évaluations doivent être validées.');
        $this->transition($application, $operator, ApplicationStatus::COMMISSION_REVIEW);
    }

    public function recordDecision(User $operator, string $applicationId, string $decision, ?float $amount = null, ?string $reason = null): void
    {
        $this->authorize($operator, 'applications.decide', 'applications.validate');
        abort_unless(in_array($decision, ['accepted', 'rejected', 'waitlisted', 'deferred'], true), 422);
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless($application->workflow_status === ApplicationStatus::COMMISSION_REVIEW->value, 422);
        DB::transaction(function () use ($operator, $application, $decision, $amount, $reason): void {
            DB::table('application_results')->updateOrInsert(['application_id' => $application->id], ['id' => (string) Str::uuid(), 'decision' => $decision, 'score' => $amount, 'reason' => $reason, 'decided_by' => $operator->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->transition($application, $operator, ApplicationStatus::DECISION_MADE, ['decided_by' => $operator->id, 'decided_at' => now()]);
        });
    }

    public function publishResult(User $operator, string $applicationId): void
    {
        $this->authorize($operator, 'applications.publish_result', 'applications.validate');
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        $result = DB::table('application_results')->where('application_id', $applicationId)->firstOrFail();
        abort_unless($application->workflow_status === ApplicationStatus::DECISION_MADE->value, 422);
        DB::transaction(function () use ($operator, $application, $result): void {
            DB::table('application_results')->where('id', $result->id)->update(['published_at' => now(), 'updated_at' => now()]);
            $this->transition($application, $operator, ApplicationStatus::RESULT_PUBLISHED, ['result_published_at' => now()]);
            DB::table('calls')->where('id', $application->call_id)->update(['status' => 'closed', 'results_published_at' => now(), 'updated_at' => now()]);
        });
    }

    public function createAward(User $operator, string $applicationId, float $amount): object
    {
        $this->authorize($operator, 'applications.award', 'applications.validate');
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless($application->workflow_status === ApplicationStatus::RESULT_PUBLISHED->value, 422);
        $result = DB::table('application_results')->where('application_id', $applicationId)->where('decision', 'accepted')->firstOrFail();
        abort_unless($result->published_at, 422);
        $maximum = DB::table('calls')->where('id', $application->call_id)->value('maximum_project_amount');
        abort_unless($amount > 0 && (! $maximum || $amount <= (float) $maximum), 422, 'Le montant attribué dépasse le montant autorisé.');
        return DB::transaction(function () use ($operator, $application, $amount, $result): object {
            $id = (string) Str::uuid();
            DB::table('application_awards')->updateOrInsert(['application_id' => $application->id], ['id' => $id, 'beneficiary_id' => $application->applicant_id, 'program_id' => $application->program_id, 'amount' => $amount, 'award_date' => today(), 'decision_reference' => $application->reference, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $this->recordFinancialAudit($operator, 'application_awards', $id, 'financial.award_created', [], ['application_id' => $application->id, 'beneficiary_id' => $application->applicant_id, 'program_id' => $application->program_id, 'amount' => $amount, 'decision_reference' => $application->reference]);
            $this->transition($application, $operator, ApplicationStatus::AWARDED);
            app(AuditLogger::class)->record('application.awarded', 'application_awards', $id, [], ['application_id' => $application->id, 'amount' => $amount, 'decision' => $result->decision]);
            return DB::table('application_awards')->where('application_id', $application->id)->first();
        });
    }

    public function sendToFinance(User $operator, string $applicationId): void
    {
        $this->authorize($operator, 'applications.finance', 'finance.authorize', 'finance.manage');
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless($application->workflow_status === ApplicationStatus::AWARDED->value, 422);
        $this->transition($application, $operator, ApplicationStatus::FINANCE_PENDING);
    }

    public function createFinancialCommitment(User $operator, string $applicationId, float $amount, string $currency = 'FCFA'): object
    {
        $this->authorize($operator, 'applications.finance', 'finance.manage');
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless(in_array($application->workflow_status, [ApplicationStatus::AWARDED->value, ApplicationStatus::FINANCE_PENDING->value], true), 422);
        $award = DB::table('application_awards')->where('application_id', $applicationId)->firstOrFail();
        abort_unless($amount > 0 && $amount <= (float) $award->amount, 422, 'L’engagement dépasse l’attribution.');
        return DB::transaction(function () use ($operator, $application, $amount, $currency): object {
            if ($application->workflow_status === ApplicationStatus::AWARDED->value) {
                $this->transition($application, $operator, ApplicationStatus::FINANCE_PENDING);
                $application = (object) [...(array) $application, 'workflow_status' => ApplicationStatus::FINANCE_PENDING->value];
            }
            $id = (string) Str::uuid();
            DB::table('financial_commitments')->insert(['id' => $id, 'application_id' => $application->id, 'program_id' => $application->program_id, 'beneficiary_id' => $application->applicant_id, 'reference' => 'ENG-'.$application->reference, 'amount' => $amount, 'budget' => $amount, 'currency' => $currency, 'fiscal_year' => today()->year, 'status' => 'soumis', 'committed_at' => today(), 'created_at' => now(), 'updated_at' => now()]);
            $this->recordFinancialAudit($operator, 'financial_commitments', $id, 'financial.commitment_created', [], ['application_id' => $application->id, 'program_id' => $application->program_id, 'beneficiary_id' => $application->applicant_id, 'amount' => $amount, 'budget' => $amount, 'fiscal_year' => today()->year]);
            $this->transition($application, $operator, ApplicationStatus::COMMITTED);
            return DB::table('financial_commitments')->where('id', $id)->first();
        });
    }

    public function prepareDisbursement(User $operator, string $applicationId, float $amount): object
    {
        $this->authorize($operator, 'applications.disburse', 'finance.authorize', 'finance.manage');
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless(in_array($application->workflow_status, [ApplicationStatus::COMMITTED->value, ApplicationStatus::DISBURSED->value], true), 422);
        $commitment = DB::table('financial_commitments')->where('application_id', $applicationId)->whereIn('status', ['soumis', 'valide'])->firstOrFail();
        $alreadyScheduled = (float) DB::table('disbursements')->where('commitment_id', $commitment->id)->sum('amount');
        abort_unless($amount > 0 && $alreadyScheduled + $amount <= (float) $commitment->amount, 422, 'Le montant du décaissement dépasse le solde engagé.');
        return DB::transaction(function () use ($operator, $application, $commitment, $amount): object {
            $id = (string) Str::uuid();
            $installmentNumber = DB::table('disbursements')->where('commitment_id', $commitment->id)->count() + 1;
            DB::table('disbursements')->insert(['id' => $id, 'commitment_id' => $commitment->id, 'reference' => 'DEC-'.$application->reference.($installmentNumber > 1 ? '-TRANCHE-'.$installmentNumber : ''), 'amount' => $amount, 'installment_number' => $installmentNumber, 'status' => 'planned', 'scheduled_for' => today(), 'created_at' => now(), 'updated_at' => now()]);
            $this->recordFinancialAudit($operator, 'disbursements', $id, 'financial.disbursement_created', [], ['commitment_id' => $commitment->id, 'amount' => $amount, 'installment_number' => $installmentNumber]);
            $this->transition($application, $operator, ApplicationStatus::DISBURSEMENT_PENDING);
            return DB::table('disbursements')->where('id', $id)->first();
        });
    }

    public function completeDisbursement(User $operator, string $applicationId, string $paymentReference): void
    {
        $this->authorize($operator, 'applications.disburse', 'finance.execute', 'finance.manage');
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        abort_unless($application->workflow_status === ApplicationStatus::DISBURSEMENT_PENDING->value, 422);
        $disbursement = DB::table('disbursements')->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id')->where('financial_commitments.application_id', $applicationId)->where('disbursements.status', 'planned')->select('disbursements.*')->firstOrFail();
        DB::transaction(function () use ($operator, $application, $disbursement, $paymentReference): void {
            $paymentService = app(FinancialPaymentService::class);
            $payment = $paymentService->initiate(Disbursement::query()->findOrFail($disbursement->id), $operator, ['amount' => $disbursement->amount, 'reference' => $paymentReference], 'workflow-disbursement:'.$disbursement->id);
            $paymentService->transition($payment, 'paid', $operator, $paymentReference);
            DB::table('disbursements')->where('id', $disbursement->id)->update(['status' => 'execute', 'disbursed_at' => today(), 'executed_at' => now(), 'processed_by' => $operator->id, 'comment' => $paymentReference, 'updated_at' => now()]);
            $this->recordFinancialAudit($operator, 'disbursements', $disbursement->id, 'financial.disbursement_completed', ['status' => $disbursement->status], ['status' => 'execute', 'payment_reference' => $paymentReference]);
            $this->transition($application, $operator, ApplicationStatus::DISBURSED, ['decided_at' => now()]);
        });
    }

    private function transition(object $application, User $actor, ApplicationStatus $next, array $extra = []): void
    {
        DB::transaction(function () use ($application, $actor, $next, $extra): void {
            DB::table('applications')->where('id', $application->id)->update(['status' => $this->legacyStatus($next), 'workflow_status' => $next->value, ...$extra, 'updated_at' => now()]);
            DB::table('application_status_histories')->insert(['id' => (string) Str::uuid(), 'application_id' => $application->id, 'changed_by' => $actor->id, 'from_status' => $application->workflow_status, 'to_status' => $next->value, 'reason' => $extra['verification_reason'] ?? null, 'changed_at' => now()]);
            app(AuditLogger::class)->record('application.workflow_transition', 'applications', $application->id, ['workflow_status' => $application->workflow_status], ['workflow_status' => $next->value, ...$extra]);
        });
        app(DashboardStatisticsService::class)->invalidate();
        try { app(NotificationService::class)->notify(User::findOrFail($application->applicant_id), 'application.status_changed', ['status' => $next->label()], ['internal', 'email'], 'workflow:'.$application->id.':'.$next->value); } catch (\Throwable) { }
    }

    private function authorize(User $operator, string ...$permissions): void
    {
        abort_unless(collect($permissions)->contains(fn (string $permission): bool => $operator->can($permission)), 403);
    }

    private function recordFinancialAudit(User $actor, string $operationType, string $operationId, string $event, array $oldValues, array $newValues): void
    {
        DB::table('financial_audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $actor->id,
            'operation_type' => $operationType,
            'operation_id' => $operationId,
            'event' => $event,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'created_at' => now(),
        ]);
    }

    private function legacyStatus(ApplicationStatus $status): string
    {
        return match ($status) {
            ApplicationStatus::DRAFT => 'brouillon', ApplicationStatus::DOCUMENTS_PENDING => 'incomplet',
            ApplicationStatus::COMPLETENESS_CHECK => 'verification', ApplicationStatus::SUBMITTED => 'soumis',
            ApplicationStatus::UNIVERSITY_REVIEW => 'recevable', ApplicationStatus::EVALUATOR_ASSIGNMENT, ApplicationStatus::EVALUATION => 'evaluation',
            ApplicationStatus::COMMISSION_REVIEW, ApplicationStatus::DECISION_MADE, ApplicationStatus::RESULT_PUBLISHED => 'decision',
            ApplicationStatus::AWARDED => 'approuve', ApplicationStatus::FINANCE_PENDING, ApplicationStatus::COMMITTED => 'engage',
            ApplicationStatus::DISBURSEMENT_PENDING => 'decaisse', ApplicationStatus::DISBURSED => 'paye',
            ApplicationStatus::REJECTED => 'rejete', ApplicationStatus::CANCELLED => 'cloture',
        };
    }
}