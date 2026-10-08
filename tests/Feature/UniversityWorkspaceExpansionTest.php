<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UniversityWorkspaceExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_university_student_detail_is_tenant_scoped(): void
    {
        [$university, $manager] = $this->universityContext();
        $otherUniversity = $this->createUniversity();
        $local = $this->createStudent($university, 'local@test.example');
        $foreign = $this->createStudent($otherUniversity, 'foreign@test.example');

        $this->actingAs($manager)->get('/university/students/'.$local)->assertOk()->assertSee('local@test.example');
        $this->actingAs($manager)->get('/university/students/'.$foreign)->assertNotFound();
    }

    public function test_university_api_is_scoped_to_authenticated_tenant(): void
    {
        [$university, $manager] = $this->universityContext();
        $otherUniversity = $this->createUniversity();
        $this->createStudent($university, 'local-api@test.example');
        $this->createStudent($otherUniversity, 'foreign-api@test.example');

        Sanctum::actingAs($manager, ['university.view', 'university.students.view']);
        $this->getJson('/api/v1/university/me')->assertOk()->assertJsonPath('data.id', $university);
        $this->getJson('/api/v1/university/students')->assertOk()->assertJsonFragment(['email' => 'local-api@test.example'])->assertJsonMissing(['email' => 'foreign-api@test.example']);
    }

    public function test_university_student_api_never_exposes_ine_login_code_hash(): void
    {
        [$university, $manager] = $this->universityContext();
        $studentId = $this->createStudent($university, 'ine-hash@test.example');
        DB::table('student_profiles')->where('user_id', $studentId)->update(['ine_login_code_hash' => password_hash('0123456789', PASSWORD_BCRYPT)]);
        Sanctum::actingAs($manager, ['university.view', 'university.students.view']);

        $this->getJson('/api/v1/university/students/'.$studentId)->assertOk()->assertJsonMissingPath('data.ine_login_code_hash');
    }

    public function test_university_profile_update_validates_institutional_fields(): void
    {
        [$university, $manager] = $this->universityContext();
        $this->actingAs($manager)->put('/university/profile', ['name' => 'Université partenaire', 'short_name' => 'UP', 'institution_type' => 'private', 'country' => 'Guinée', 'email' => 'contact@up.test', 'responsible_email' => 'direction@up.test'])->assertRedirect();
        $this->assertDatabaseHas('universities', ['id' => $university, 'institution_type' => 'private', 'email' => 'contact@up.test']);
    }

    public function test_university_cannot_request_corrections_on_a_disbursed_application(): void
    {
        [$university, $manager] = $this->universityContext();
        $student = $this->createStudent($university, 'paid-student@test.example');
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        $applicationId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme clos', 'code' => 'CLS-'.Str::random(6), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel clos', 'reference' => 'CLS-'.Str::random(6), 'opens_at' => today()->subDay(), 'closes_at' => today(), 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('applications')->insert(['id' => $applicationId, 'call_id' => $callId, 'program_id' => $programId, 'applicant_id' => $student, 'reference' => 'CLS-'.Str::random(6), 'status' => 'paye', 'workflow_status' => 'disbursed', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($manager, ['university.view', 'university.applications.correction']);

        $this->postJson('/api/v1/university/applications/'.$applicationId.'/request-correction', ['reason' => 'Demande tardive'])->assertNotFound();

        $this->assertDatabaseHas('applications', ['id' => $applicationId, 'status' => 'paye', 'workflow_status' => 'disbursed']);
    }

    private function universityContext(): array
    {
        $university = $this->createUniversity();
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');
        DB::table('university_users')->where('university_id', $university)->where('user_id', $manager->id)->update(['role' => 'admin', 'updated_at' => now()]);
        return [$university, $manager];
    }

    private function createUniversity(): string
    {
        $id = (string) Str::uuid();
        DB::table('universities')->insert(['id' => $id, 'name' => 'Université '.Str::random(6), 'code' => 'U-'.Str::random(8), 'country' => 'Guinée', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    private function createStudent(string $university, string $email): int
    {
        $student = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active', 'email' => $email]);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'university_id' => $university, 'inee' => 'INEE-'.Str::random(8), 'validation_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        return $student->id;
    }
}
