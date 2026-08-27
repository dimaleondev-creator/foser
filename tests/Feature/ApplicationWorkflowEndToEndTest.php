<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\User;
use App\Services\ApplicationEvaluationService;
use App\Services\ApplicationWorkflowService;
use App\Services\StudentApplicationWorkflow;
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
        app(ApplicationEvaluationService::class)->submitEvaluation($evaluation->id, $evaluator->id, [$criterion => 18]);
        $workflow->sendToCommission($admin, $application->id);
        $workflow->recordDecision($admin, $application->id, 'accepted', 4000, 'Retenu');
        $workflow->publishResult($admin, $application->id);
        $workflow->createAward($admin, $application->id, 4000);
        $workflow->sendToFinance($admin, $application->id);
        $workflow->createFinancialCommitment($admin, $application->id, 4000);
        $workflow->prepareDisbursement($admin, $application->id, 4000);
        $workflow->completeDisbursement($admin, $application->id, 'PAY-001');

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'workflow_status' => ApplicationStatus::DISBURSED->value]);
        $this->assertDatabaseHas('application_awards', ['application_id' => $application->id, 'amount' => 4000]);
        $this->assertDatabaseHas('disbursements', ['reference' => 'DEC-'.$application->reference, 'status' => 'execute']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'application.workflow_transition', 'auditable_id' => $application->id]);
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
