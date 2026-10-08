<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiResourceScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_can_only_list_and_show_their_own_applications(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();
        $ownApplication = $this->createTestApplication($student->id);
        $otherApplication = $this->createTestApplication($otherStudent->id);
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/applications?per_page=1&status=soumis')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownApplication)
            ->assertJsonPath('pagination.per_page', 1)->assertJsonPath('success', true);
        $this->getJson('/api/v1/applications?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page')->assertJsonPath('success', false);
        $this->getJson('/api/v1/applications/'.$otherApplication)->assertNotFound();
        $this->getJson('/api/v1/applications/'.$ownApplication)->assertOk();
    }

    public function test_evaluator_can_only_read_assigned_applications_and_evaluations(): void
    {
        $evaluator = User::factory()->create(['account_type' => 'evaluateur']);
        $evaluator->assignRole('evaluateur');
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();
        $assignedApplication = $this->createTestApplication($student->id);
        $unassignedApplication = $this->createTestApplication($otherStudent->id);
        $assignedEvaluation = $this->createEvaluation($assignedApplication, $evaluator->id);
        $otherEvaluation = $this->createEvaluation($unassignedApplication, User::factory()->create(['account_type' => 'evaluateur'])->id);
        Sanctum::actingAs($evaluator);

        $this->getJson('/api/v1/applications')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assignedApplication)
            ->assertJsonMissingPath('data.0.applicant_id')->assertJsonMissingPath('data.0.applicant_note');
        $this->getJson('/api/v1/applications/'.$unassignedApplication)->assertNotFound();
        $this->getJson('/api/v1/evaluations')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assignedEvaluation);
        $this->getJson('/api/v1/evaluations/'.$otherEvaluation)->assertNotFound();
    }

    public function test_university_generic_student_and_application_reads_are_tenant_scoped(): void
    {
        $universityId = $this->createUniversity();
        $otherUniversityId = $this->createUniversity();
        $universityUser = User::factory()->create(['account_type' => 'universite', 'university_id' => $universityId]);
        $universityUser->assignRole('universite');
        $localStudent = $this->createStudent($universityId);
        $foreignStudent = $this->createStudent($otherUniversityId);
        $localApplication = $this->createTestApplication($localStudent->id);
        $foreignApplication = $this->createTestApplication($foreignStudent->id);
        Sanctum::actingAs($universityUser);

        $this->getJson('/api/v1/students')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $localStudent->id)->assertJsonMissingPath('data.0.inee');
        $this->getJson('/api/v1/students/'.$foreignStudent->id)->assertNotFound();
        $this->getJson('/api/v1/university/students')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', $localStudent->id)
            ->assertJsonMissingPath('data.0.inee')->assertJsonMissingPath('data.0.date_of_birth')
            ->assertJsonPath('pagination.per_page', 25);
        $this->getJson('/api/v1/university/students/'.$foreignStudent->id)->assertNotFound();
        $this->getJson('/api/v1/applications')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $localApplication)->assertJsonMissingPath('data.0.applicant_id')->assertJsonMissingPath('data.0.applicant_note');
        $this->getJson('/api/v1/applications/'.$foreignApplication)->assertNotFound();
    }

    public function test_student_claims_are_scoped_on_index_and_detail(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();
        $ownClaim = $this->createClaim($student->id);
        $otherClaim = $this->createClaim($otherStudent->id);
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/claims')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownClaim);
        $this->getJson('/api/v1/claims/'.$otherClaim)->assertNotFound();
    }

    public function test_notification_delivery_detail_is_private_to_its_owner(): void
    {
        $user = $this->createStudent();
        $otherUser = $this->createStudent();
        $ownNotification = $this->createNotification($user->id);
        $otherNotification = $this->createNotification($otherUser->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownNotification);
        $this->getJson('/api/v1/notifications/'.$otherNotification)->assertNotFound();
    }

    public function test_researcher_projects_are_scoped_to_ownership_or_membership(): void
    {
        $researcher = User::factory()->create(['account_type' => 'researcher']);
        $researcher->assignRole('researcher');
        $otherResearcher = User::factory()->create(['account_type' => 'researcher']);
        $otherResearcher->assignRole('researcher');
        $ownProject = $this->createResearchProject($researcher->id);
        $otherProject = $this->createResearchProject($otherResearcher->id);
        Sanctum::actingAs($researcher);

        $this->getJson('/api/v1/research-projects')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownProject);
        $this->getJson('/api/v1/research-projects/'.$otherProject)->assertNotFound();
    }

    public function test_partner_api_call_access_excludes_drafts_and_future_publications(): void
    {
        $partner = User::factory()->create(['account_type' => 'partenaire']);
        $partner->assignRole('partenaire');
        $programId = (string) Str::uuid();
        DB::table('programs')->insert([
            'id' => $programId, 'name' => 'Appels partenaires', 'code' => 'CALL-'.Str::random(8),
            'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $publishedCall = $this->createCall($programId, 'published', now()->subDay());
        $draftCall = $this->createCall($programId, 'draft', now()->subDay());
        $scheduledCall = $this->createCall($programId, 'published', now()->addDay());
        Sanctum::actingAs($partner);

        $this->getJson('/api/v1/calls')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $publishedCall);
        $this->getJson('/api/v1/calls/'.$draftCall)->assertNotFound();
        $this->getJson('/api/v1/calls/'.$scheduledCall)->assertNotFound();
    }

    public function test_partner_program_api_access_excludes_unpublished_programs(): void
    {
        $partner = User::factory()->create(['account_type' => 'partenaire']);
        $partner->assignRole('partenaire');
        $published = $this->createProgram('published');
        $draft = $this->createProgram('draft');
        Sanctum::actingAs($partner);

        $this->getJson('/api/v1/programs')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $published);
        $this->getJson('/api/v1/programs/'.$draft)->assertNotFound();
    }

    public function test_university_and_researcher_api_reads_are_scoped_to_their_own_records(): void
    {
        $universityId = $this->createUniversity();
        $otherUniversityId = $this->createUniversity();
        $universityUser = User::factory()->create(['account_type' => 'universite', 'university_id' => $universityId]);
        $universityUser->assignRole('universite');
        $localResearcher = $this->createResearcher($universityId);
        $foreignResearcher = $this->createResearcher($otherUniversityId);
        Sanctum::actingAs($universityUser);

        $this->getJson('/api/v1/researchers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $localResearcher);
        $this->getJson('/api/v1/researchers/'.$foreignResearcher)->assertNotFound();

        $researcherUser = User::factory()->create(['account_type' => 'researcher']);
        $researcherUser->assignRole('researcher');
        $ownResearcher = $this->createResearcher($universityId, $researcherUser->id);
        Sanctum::actingAs($researcherUser);
        $this->getJson('/api/v1/researchers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownResearcher);
        $this->getJson('/api/v1/researchers/'.$localResearcher)->assertNotFound();
    }

    public function test_pending_researcher_api_access_returns_standard_json_forbidden_response(): void
    {
        $researcher = User::factory()->create(['account_type' => 'researcher', 'status' => 'pending']);
        $researcher->assignRole('researcher');
        DB::table('researcher_profiles')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $researcher->id, 'researcher_number' => 'PENDING-'.Str::random(8), 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Sanctum::actingAs($researcher);

        $this->getJson('/api/v1/researchers/me')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('pagination', null);
    }

    public function test_results_and_awards_are_limited_to_the_owning_student(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();
        $ownApplication = $this->createTestApplication($student->id);
        $otherApplication = $this->createTestApplication($otherStudent->id);
        $ownResult = $this->createResult($ownApplication);
        $this->createResult($otherApplication);
        $ownAward = $this->createAward($ownApplication, $student->id);
        $this->createAward($otherApplication, $otherStudent->id);
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/results')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownResult);
        $this->getJson('/api/v1/awards')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownAward);
        $this->getJson('/api/v1/disbursements')->assertForbidden();
    }

    public function test_only_finance_authorized_users_can_read_disbursements(): void
    {
        $student = $this->createStudent();
        Sanctum::actingAs($student);
        $this->getJson('/api/v1/disbursements')->assertForbidden();

        $finance = User::factory()->create(['account_type' => 'agent_finance']);
        $finance->assignRole('agent_finance');
        Sanctum::actingAs($finance);
        $this->getJson('/api/v1/disbursements?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    private function createStudent(?string $universityId = null): User
    {
        $student = User::factory()->create(['account_type' => 'etudiant']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $student->id,
            'university_id' => $universityId,
            'inee' => 'INEE-'.Str::random(8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $student;
    }

    private function createUniversity(): string
    {
        $id = (string) Str::uuid();
        DB::table('universities')->insert([
            'id' => $id,
            'name' => 'Université '.Str::random(8),
            'code' => 'U-'.Str::random(8),
            'country' => 'Guinée',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createTestApplication(int $applicantId): string
    {
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        $applicationId = (string) Str::uuid();
        $reference = 'APP-'.Str::random(8);
        DB::table('programs')->insert([
            'id' => $programId,
            'name' => 'Programme API',
            'code' => $reference,
            'type' => 'education',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('calls')->insert([
            'id' => $callId,
            'program_id' => $programId,
            'title' => 'Appel API',
            'reference' => $reference,
            'opens_at' => today()->subDay(),
            'closes_at' => today()->addDay(),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('applications')->insert([
            'id' => $applicationId,
            'call_id' => $callId,
            'program_id' => $programId,
            'applicant_id' => $applicantId,
            'reference' => $reference,
            'status' => 'soumis',
            'workflow_status' => 'submitted',
            'applicant_note' => 'Note confidentielle',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $applicationId;
    }

    private function createClaim(int $claimantId): string
    {
        $id = (string) Str::uuid();
        DB::table('claims')->insert([
            'id' => $id,
            'claimant_id' => $claimantId,
            'reference' => 'CLM-'.Str::random(8),
            'subject' => 'Réclamation',
            'description' => 'Description privée',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createNotification(int $userId): string
    {
        $id = (string) Str::uuid();
        DB::table('notification_deliveries')->insert([
            'id' => $id,
            'user_id' => $userId,
            'event' => 'application.updated',
            'channel' => 'internal',
            'idempotency_key' => 'notification-'.Str::random(12),
            'status' => 'sent',
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createResearchProject(int $researcherId): string
    {
        $id = (string) Str::uuid();
        DB::table('research_projects')->insert([
            'id' => $id,
            'principal_researcher_id' => $researcherId,
            'title' => 'Projet de recherche',
            'reference' => 'PRJ-'.Str::random(8),
            'abstract' => 'Résumé',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createCall(string $programId, string $status, \Illuminate\Support\Carbon $publishedAt): string
    {
        $id = (string) Str::uuid();
        DB::table('calls')->insert([
            'id' => $id, 'program_id' => $programId, 'title' => 'Appel '.Str::random(8),
            'reference' => 'CALL-'.Str::random(8), 'opens_at' => today()->subDay(),
            'closes_at' => today()->addDays(10), 'status' => $status, 'published_at' => $publishedAt,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function createResearcher(string $universityId, ?int $userId = null): string
    {
        $userId ??= User::factory()->create(['account_type' => 'researcher'])->id;
        $id = (string) Str::uuid();
        DB::table('researcher_profiles')->insert([
            'id' => $id, 'user_id' => $userId, 'university_id' => $universityId,
            'researcher_number' => 'RES-'.Str::random(8), 'research_domain' => 'Sciences',
            'speciality' => 'Biologie', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function createResult(string $applicationId): string
    {
        $id = (string) Str::uuid();
        DB::table('application_results')->insert([
            'id' => $id, 'application_id' => $applicationId, 'decision' => 'accepted', 'score' => 90,
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    private function createAward(string $applicationId, int $beneficiaryId): string
    {
        $programId = DB::table('applications')->where('id', $applicationId)->value('program_id');
        $id = (string) Str::uuid();
        DB::table('application_awards')->insert([
            'id' => $id, 'application_id' => $applicationId, 'beneficiary_id' => $beneficiaryId,
            'program_id' => $programId, 'amount' => 500, 'award_date' => today(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    private function createProgram(string $status): string
    {
        $id = (string) Str::uuid();
        DB::table('programs')->insert([
            'id' => $id, 'name' => 'Programme '.Str::random(8), 'code' => 'PRG-'.Str::random(8),
            'type' => 'education', 'status' => $status, 'budget' => 1000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function createEvaluation(string $applicationId, int $evaluatorId): string
    {
        $id = (string) Str::uuid();
        DB::table('evaluations')->insert([
            'id' => $id,
            'application_id' => $applicationId,
            'evaluator_id' => $evaluatorId,
            'status' => 'assigned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}