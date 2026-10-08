<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\User;
use App\Services\ApplicationEvaluationService;
use App\Services\ApplicationWorkflowService;
use App\Services\FinancialWorkflow;
use App\Services\StudentApplicationWorkflow;
use App\Enums\FinancialOperationStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplicationWorkflowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_application_reaches_disbursed_through_canonical_workflow(): void
    {
        [$student, $callId, $programId] = $this->context();
        $admin = User::factory()->create(['account_type' => 'admin']);
        $admin->assignRole('admin');
        $evaluator = User::factory()->create(['account_type' => 'evaluateur']);
        $evaluator->assignRole('evaluateur');
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update([
            'project_title' => 'Projet complet', 'summary' => 'Résumé', 'description' => 'Description', 'domain' => 'Domaine',
            'objectives' => 'Objectifs', 'methodology' => 'Méthode', 'calendar' => 'Calendrier', 'budget' => 5000,
        ]);
        $workflow = app(ApplicationWorkflowService::class);

        $workflow->submitApplication($student, $application->id);
        $this->assertSame(ApplicationStatus::SUBMITTED->value, DB::table('applications')->where('id', $application->id)->value('workflow_status'));
        $workflow->verifyCompleteness($admin, $application->id);
        $workflow->approveUniversityReview($admin, $application->id);
        $workflow->assignEvaluators($admin, $application->id, [$evaluator->id]);
        $evaluation = DB::table('evaluations')->where('application_id', $application->id)->first();
        $criterion = (string) Str::uuid();
        DB::table('evaluation_criteria')->insert(['id' => $criterion, 'program_id' => $programId, 'name' => 'Qualité', 'maximum_score' => 20, 'weight' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $evaluationService = app(ApplicationEvaluationService::class);
        $evaluationService->startEvaluation($evaluation->id, $evaluator->id);
        $evaluationService->submitEvaluation($evaluation->id, $evaluator->id, [$criterion => 18], 'Projet pertinent.', [$criterion => 'Méthode convaincante.']);
        $evaluationService->validateEvaluation($admin, $evaluation->id);
        $this->assertDatabaseHas('evaluations', ['id' => $evaluation->id, 'status' => 'validated', 'validated_by' => $admin->id]);
        $workflow->sendToCommission($admin, $application->id);
        $workflow->recordDecision($admin, $application->id, 'accepted', 4000, 'Retenu');
        $workflow->publishResult($admin, $application->id);
        $workflow->createAward($admin, $application->id, 4000);
        try {
            $workflow->createFinancialCommitment($admin, $application->id, 5000);
            $this->fail('A commitment above the award must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'workflow_status' => ApplicationStatus::AWARDED->value]);
        $this->assertDatabaseHas('application_awards', ['application_id' => $application->id, 'beneficiary_id' => $student->id, 'program_id' => $programId, 'amount' => 4000, 'decision_reference' => $application->reference, 'status' => 'active']);
        $this->assertDatabaseMissing('financial_commitments', ['application_id' => $application->id]);
        $commitment = $workflow->createFinancialCommitment($admin, $application->id, 4000);
        $this->assertSame($programId, $commitment->program_id);
        $this->assertSame($student->id, (int) $commitment->beneficiary_id);
        $this->assertSame(4000.0, (float) $commitment->budget);
        $this->assertSame((int) today()->year, (int) $commitment->fiscal_year);
        $this->assertDatabaseHas('financial_audit_logs', ['operation_type' => 'financial_commitments', 'operation_id' => $commitment->id, 'event' => 'financial.commitment_created', 'user_id' => $admin->id]);
        app(FinancialWorkflow::class)->transition('financial_commitments', $commitment->id, FinancialOperationStatus::VALIDE, $admin, 'Budget validé.');
        try {
            $workflow->prepareDisbursement($admin, $application->id, -1);
            $this->fail('A negative disbursement must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseMissing('disbursements', ['commitment_id' => DB::table('financial_commitments')->where('application_id', $application->id)->value('id')]);
        $workflow->prepareDisbursement($admin, $application->id, 4000);
        $workflow->completeDisbursement($admin, $application->id, 'PAY-001');

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'workflow_status' => ApplicationStatus::DISBURSED->value]);
        $this->assertDatabaseHas('application_awards', ['application_id' => $application->id, 'amount' => 4000]);
        $this->assertDatabaseHas('disbursements', ['reference' => 'DEC-'.$application->reference, 'status' => 'execute']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'application.workflow_transition', 'auditable_id' => $application->id]);

        try {
            $workflow->createAward($admin, $application->id, 1000);
            $this->fail('An award must not be recreated after disbursement.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'workflow_status' => ApplicationStatus::DISBURSED->value]);
        $this->assertDatabaseHas('application_awards', ['application_id' => $application->id, 'amount' => 4000]);
    }

    private function context(): array
    {
        $student = User::factory()->create(['account_type' => 'etudiant']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => 'INEE-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme workflow', 'code' => 'APP-'.Str::random(6), 'type' => 'research', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel workflow', 'reference' => 'APP-'.Str::random(6), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        return [$student, $callId, $programId];
    }
}
