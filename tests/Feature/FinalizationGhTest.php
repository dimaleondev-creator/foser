<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use App\Services\ApplicationEvaluationService;
use App\Services\StudentApplicationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation']);
        DB::table('evaluation_criteria')->insert(['id' => (string) Str::uuid(), 'program_id' => $programId, 'name' => 'Pertinence', 'maximum_score' => 20, 'weight' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $evaluation = app(ApplicationEvaluationService::class)->assignEvaluator($application->id, $evaluator->id);

        $this->actingAs($other)->get('/evaluateur/candidatures/'.$application->id)->assertNotFound();
        $this->actingAs($evaluator)->get('/evaluateur/candidatures/'.$application->id)->assertOk();
        $this->actingAs($evaluator)->post('/evaluateur/candidatures/'.$application->id.'/evaluation', ['scores' => [(string) DB::table('evaluation_criteria')->value('id') => 18]])->assertRedirect();
        $this->actingAs($evaluator)->post('/evaluateur/candidatures/'.$application->id.'/evaluation', ['scores' => [(string) DB::table('evaluation_criteria')->value('id') => 19]])->assertSessionHasErrors('evaluation');
    }

    public function test_inactive_evaluator_cannot_be_assigned(): void
    {
        [$student, $callId, $programId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation']);
        $inactive = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'disabled']);
        $inactive->assignRole('evaluateur');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ApplicationEvaluationService::class)->assignEvaluator($application->id, $inactive->id);
    }

    public function test_duplicate_evaluator_assignment_is_rejected(): void
    {
        [$student, $callId] = $this->candidateContext();
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update(['status' => 'evaluation']);
        $evaluator = User::factory()->create(['account_type' => 'evaluateur', 'status' => 'active']);
        $evaluator->assignRole('evaluateur');
        $service = app(ApplicationEvaluationService::class);
        $service->assignEvaluator($application->id, $evaluator->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assignEvaluator($application->id, $evaluator->id);
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