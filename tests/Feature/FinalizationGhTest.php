<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use App\Services\ApplicationEvaluationService;
use App\Services\StudentApplicationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinalizationGhTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_candidate_can_update_only_owned_draft_and_submitted_draft_is_locked(): void
    {
        [$student, $callId, $programId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        $payload = ['project_title' => 'Projet test', 'summary' => 'Résumé suffisamment détaillé', 'description' => 'Description complète', 'domain' => 'Recherche', 'objectives' => 'Objectifs', 'methodology' => 'Méthode', 'calendar' => 'Calendrier', 'budget' => 1000, 'team' => 'Équipe'];

        $this->actingAs($student)->put('/student/applications/'.$application->id, $payload)->assertRedirect();
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'project_title' => 'Projet test']);
        app(StudentApplicationWorkflow::class)->transition($student, $application->id, \App\Enums\StudentApplicationStatus::SOUMIS);
        $this->actingAs($student)->put('/student/applications/'.$application->id, $payload)->assertForbidden();
    }

    public function test_evaluator_only_sees_own_assignment_and_submission_is_locked(): void
    {
        $evaluator = User::factory()->create(['account_type' => 'evaluateur']);
        $evaluator->assignRole('evaluateur');
        $other = User::factory()->create(['account_type' => 'evaluateur']);
        $other->assignRole('evaluateur');
        [$student, $callId, $programId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation', 'workflow_status' => 'evaluation']);
        DB::table('evaluation_criteria')->insert(['id' => (string) Str::uuid(), 'program_id' => $programId, 'name' => 'Pertinence', 'maximum_score' => 20, 'weight' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $evaluation = app(ApplicationEvaluationService::class)->assignEvaluator($application->id, $evaluator->id);

        $this->actingAs($other)->get('/evaluateur/candidatures/'.$application->id)->assertNotFound();
        $this->post('/evaluateur/candidatures/'.$application->id.'/evaluation', ['scores' => [(string) DB::table('evaluation_criteria')->value('id') => 17]])->assertNotFound();
        $this->actingAs($evaluator)->get('/admin/applications/'.$application->id.'/details')->assertForbidden();
        $this->get('/evaluateur/candidatures/'.$application->id)->assertOk();
        $this->assertDatabaseHas('evaluations', ['id' => $evaluation->id, 'status' => 'in_progress']);
        $criterionId = (string) DB::table('evaluation_criteria')->value('id');
        $this->post('/evaluateur/candidatures/'.$application->id.'/evaluation', ['scores' => [$criterionId => 18], 'comments' => [$criterionId => 'Bon ancrage scientifique.'], 'comment' => 'Avis favorable.'])->assertRedirect();
        $this->assertDatabaseHas('evaluation_scores', ['evaluation_id' => $evaluation->id, 'criterion_id' => $criterionId, 'score' => 18, 'comment' => 'Bon ancrage scientifique.']);
        $this->assertDatabaseHas('evaluations', ['id' => $evaluation->id, 'status' => 'submitted', 'comment' => 'Avis favorable.']);
        $this->get('/evaluateur/candidatures/'.$application->id)->assertOk()->assertSee('Grille soumise')->assertSee('Bon ancrage scientifique.');
        $this->post(route('admin.evaluations.validate', $evaluation->id))->assertForbidden();
        $this->actingAs($evaluator)->post('/evaluateur/candidatures/'.$application->id.'/evaluation', ['scores' => [(string) DB::table('evaluation_criteria')->value('id') => 19]])->assertSessionHasErrors('evaluation');

        Auth::logout();
        $admin = User::factory()->create(['account_type' => 'admin']);
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.applications.show', $application->id))
            ->assertOk()->assertSee('Bon ancrage scientifique.')->assertSee('Valider l’évaluation');
        $this->post(route('admin.evaluations.validate', $evaluation->id))->assertRedirect();
        $this->assertDatabaseHas('evaluations', ['id' => $evaluation->id, 'status' => 'validated', 'validated_by' => $admin->id]);
        $this->actingAs($evaluator)->post('/evaluateur/candidatures/'.$application->id.'/evaluation', ['scores' => [$criterionId => 20]])->assertSessionHasErrors('evaluation');
    }

    public function test_inactive_evaluator_cannot_be_assigned(): void
    {
        [$student, $callId, $programId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation', 'workflow_status' => 'evaluation']);
        $inactive = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'disabled']);
        $inactive->assignRole('evaluateur');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ApplicationEvaluationService::class)->assignEvaluator($application->id, $inactive->id);
    }

    public function test_duplicate_evaluator_assignment_is_rejected(): void
    {
        [$student, $callId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation', 'workflow_status' => 'evaluation']);
        $evaluator = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'active']);
        $evaluator->assignRole('evaluateur');
        $service = app(ApplicationEvaluationService::class);
        $service->assignEvaluator($application->id, $evaluator->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assignEvaluator($application->id, $evaluator->id);
    }

    public function test_evaluator_expertise_is_checked_and_assignment_can_be_reassigned_before_submission(): void
    {
        [$student, $callId, $programId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation', 'workflow_status' => 'evaluation', 'domain' => 'Informatique']);
        $oldEvaluator = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'active', 'evaluation_expertise' => ['Informatique']]);
        $oldEvaluator->assignRole('evaluateur');
        $nextEvaluator = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'active', 'evaluation_expertise' => ['Informatique', 'Données']]);
        $nextEvaluator->assignRole('evaluateur');
        $mismatchEvaluator = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'active', 'evaluation_expertise' => ['Économie']]);
        $mismatchEvaluator->assignRole('evaluateur');
        $service = app(ApplicationEvaluationService::class);
        $assignment = $service->assignEvaluator($application->id, $oldEvaluator->id);

        try {
            $service->reassignEvaluator($assignment->id, $mismatchEvaluator->id);
            $this->fail('An evaluator with a non-matching expertise must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('evaluator', $exception->errors());
        }

        $admin = User::factory()->create(['account_type' => 'admin']);
        $admin->assignRole('admin');
        $this->actingAs($admin)->put(route('admin.applications.evaluators.reassign', [$application->id, $assignment->id]), ['evaluator_id' => $nextEvaluator->id])->assertRedirect();
        $reassigned = DB::table('evaluations')->where('id', $assignment->id)->first();
        $this->assertSame($nextEvaluator->id, $reassigned->evaluator_id);
        $this->assertSame('assigned', $reassigned->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'evaluation.reassigned', 'auditable_id' => $assignment->id, 'user_id' => $admin->id]);
        $this->actingAs($oldEvaluator)->get('/evaluateur/candidatures/'.$application->id)->assertNotFound();
        $this->actingAs($nextEvaluator)->get('/evaluateur/candidatures/'.$application->id)->assertOk();
    }

    public function test_evaluator_document_download_is_limited_to_documents_on_own_assignment(): void
    {
        Storage::fake('local');
        $evaluator = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'active']);
        $evaluator->assignRole('evaluateur');
        [$student, $callId, $programId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation', 'workflow_status' => 'evaluation']);
        app(ApplicationEvaluationService::class)->assignEvaluator($application->id, $evaluator->id);
        $documentId = (string) Str::uuid();
        DB::table('documents')->insert(['id' => $documentId, 'uploaded_by' => $student->id, 'title' => 'Budget', 'document_type' => 'budget', 'disk' => 'local', 'path' => 'evaluator-documents/budget.pdf', 'mime_type' => 'application/pdf', 'size' => 12, 'visibility' => 'private', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('application_documents')->insert(['id' => (string) Str::uuid(), 'application_id' => $application->id, 'document_id' => $documentId, 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk('local')->put('evaluator-documents/budget.pdf', 'private budget');

        $this->actingAs($evaluator)->get('/evaluateur/candidatures/'.$application->id)
            ->assertOk()->assertSee('Budget')->assertDontSee($student->email)->assertDontSee(DB::table('student_profiles')->where('user_id', $student->id)->value('inee'));
        $this->get('/evaluateur/candidatures/'.$application->id.'/documents/'.$documentId)->assertOk();
        $this->get('/evaluateur/candidatures/'.(string) Str::uuid().'/documents/'.$documentId)->assertNotFound();
    }

    public function test_evaluator_api_requires_start_and_does_not_expose_internal_fields(): void
    {
        $evaluator = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'active']);
        $evaluator->assignRole('evaluateur');
        [$student, $callId, $programId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation', 'workflow_status' => 'evaluation']);
        $criterionId = (string) Str::uuid();
        DB::table('evaluation_criteria')->insert(['id' => $criterionId, 'program_id' => $programId, 'name' => 'Impact', 'maximum_score' => 20, 'weight' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $assignment = app(ApplicationEvaluationService::class)->assignEvaluator($application->id, $evaluator->id);
        Sanctum::actingAs($evaluator, ['*']);

        $this->postJson('/api/evaluator/assignments/'.$assignment->id.'/start')->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->postJson('/api/evaluator/assignments/'.$assignment->id.'/evaluation', [
            'scores' => [$criterionId => 16], 'comments' => [$criterionId => 'Impact mesurable.'], 'comment' => 'Avis favorable.',
        ])->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->getJson('/api/v1/evaluations/'.$assignment->id)
            ->assertOk()->assertJsonMissingPath('data.comment')->assertJsonMissingPath('data.evaluator_id');
        $this->assertDatabaseHas('evaluation_scores', ['evaluation_id' => $assignment->id, 'criterion_id' => $criterionId, 'comment' => 'Impact mesurable.']);
        $this->assertFalse($evaluator->fresh()->can('evaluations.validate'));
    }

    public function test_legacy_result_publication_cannot_skip_the_commission_stage(): void
    {
        [$student, $callId, $programId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation', 'workflow_status' => 'evaluation']);
        $evaluator = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'active']);
        $evaluator->assignRole('evaluateur');
        $admin = User::factory()->create(['account_type' => 'admin']);
        $admin->assignRole('admin');
        app(ApplicationEvaluationService::class)->assignEvaluator($application->id, $evaluator->id);
        $this->actingAs($admin)->post('/admin/applications/'.$application->id.'/result', ['decision' => 'accepted'])->assertStatus(422);
        $this->assertDatabaseMissing('application_results', ['application_id' => $application->id]);
    }

    public function test_evaluation_cannot_be_submitted_with_missing_program_criteria(): void
    {
        [$student, $callId, $programId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation', 'workflow_status' => 'evaluation']);
        $evaluator = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'active']);
        $evaluator->assignRole('evaluateur');
        $criteria = [(string) Str::uuid(), (string) Str::uuid()];
        foreach ($criteria as $index => $criterionId) {
            DB::table('evaluation_criteria')->insert(['id' => $criterionId, 'program_id' => $programId, 'name' => 'Critère '.$index, 'maximum_score' => 20, 'weight' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $evaluation = app(ApplicationEvaluationService::class)->assignEvaluator($application->id, $evaluator->id);
        app(ApplicationEvaluationService::class)->startEvaluation($evaluation->id, $evaluator->id);

        try {
            app(ApplicationEvaluationService::class)->submitEvaluation($evaluation->id, $evaluator->id, [$criteria[0] => 18]);
            $this->fail('An evaluation with missing criteria must not be submitted.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('scores', $exception->errors());
        }

        $this->assertDatabaseHas('evaluations', ['id' => $evaluation->id, 'status' => 'in_progress']);
    }

    public function test_candidate_api_is_ownership_scoped(): void
    {
        [$student, $callId] = $this->candidateContext();
        $other = User::factory()->create(['account_type' => 'etudiant']);
        $other->assignRole('etudiant');
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        Sanctum::actingAs($other, ['*']);
        $this->getJson('/api/candidate/candidatures/'.$application->id)->assertNotFound();
    }

    public function test_admin_can_open_application_detail_with_operational_data(): void
    {
        [$student, $callId, $programId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        $admin = User::factory()->create(['account_type' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.applications.show', $application->id))
            ->assertOk()
            ->assertSee($application->reference)
            ->assertSee('Informations')
            ->assertSee('Historique');
    }

    private function candidateContext(): array
    {
        $student = User::factory()->create(['account_type' => 'etudiant']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => 'ETU-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme G', 'code' => 'G-'.Str::random(5), 'type' => 'research', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel G', 'reference' => 'G-'.Str::random(5), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        return [$student, $callId, $programId];
    }
}