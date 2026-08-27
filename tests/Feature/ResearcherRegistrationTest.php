<?php

namespace Tests\Feature;

use App\Mail\ResearcherAccountApprovedMail;
use App\Mail\ResearcherRegistrationReceivedMail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResearcherRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_researcher_registration_creates_pending_profile_and_notifies_admin(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $response = $this->post('/researcher/register', $this->registrationPayload());
        $researcher = User::where('email', 'researcher@example.test')->firstOrFail();
        $reference = DB::table('researcher_profiles')->where('user_id', $researcher->id)->value('registration_reference');
        $response->assertRedirect(route('researcher.pending', ['reference' => $reference]));
        $this->assertSame('pending', $researcher->status);
        $this->assertTrue($researcher->hasRole('researcher'));
        $this->assertDatabaseHas('researcher_profiles', ['user_id' => $researcher->id, 'status' => 'pending']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id, 'type' => 'researcher.registration.pending']);
        Mail::assertSent(ResearcherRegistrationReceivedMail::class);
        $this->actingAs($researcher)->get(route('researcher.dashboard'))->assertRedirect(route('researcher.pending'));
    }

    public function test_admin_can_approve_and_approved_researcher_can_login(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        $this->post('/researcher/register', $this->registrationPayload());
        $researcher = User::where('email', 'researcher@example.test')->firstOrFail();
        $profileId = DB::table('researcher_profiles')->where('user_id', $researcher->id)->value('id');

        $this->actingAs($admin)->post(route('admin.researchers.approve', $profileId))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $researcher->id, 'status' => 'active']);
        $this->assertDatabaseHas('researcher_profiles', ['id' => $profileId, 'status' => 'approved', 'approved_by' => $admin->id]);
        Mail::assertSent(ResearcherAccountApprovedMail::class);
        $this->post('/logout');
        $this->post('/researcher/login', ['email' => 'researcher@example.test', 'password' => 'password123'])->assertRedirect(route('researcher.dashboard'));
    }

    public function test_rejected_and_suspended_researchers_are_blocked(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        $this->post('/researcher/register', $this->registrationPayload());
        $researcher = User::where('email', 'researcher@example.test')->firstOrFail();
        $profileId = DB::table('researcher_profiles')->where('user_id', $researcher->id)->value('id');

        $this->actingAs($admin)->post(route('admin.researchers.reject', $profileId), ['reason' => 'Profil incomplet'])->assertRedirect();
        $this->post('/logout');
        $this->post('/researcher/login', ['email' => $researcher->email, 'password' => 'password123'])->assertRedirect(route('researcher.rejected'));

        DB::table('researcher_profiles')->where('id', $profileId)->update(['status' => 'approved']);
        DB::table('users')->where('id', $researcher->id)->update(['status' => 'active']);
        $this->actingAs($admin)->post(route('admin.researchers.suspend', $profileId), ['reason' => 'Contrôle requis'])->assertRedirect();
        $this->post('/logout');
        $this->post('/researcher/login', ['email' => $researcher->email, 'password' => 'password123'])->assertRedirect(route('researcher.suspended'));
    }

    public function test_researcher_cannot_submit_an_incomplete_project(): void
    {
        $researcher = User::factory()->create(['account_type' => 'researcher', 'status' => 'active']);
        $researcher->assignRole('researcher');

        $this->actingAs($researcher)->post(route('researcher.projects.create'), ['title' => 'Projet incomplet'])
            ->assertRedirect();
        $projectId = DB::table('research_projects')->where('principal_researcher_id', $researcher->id)->value('id');

        $this->actingAs($researcher)->post(route('researcher.projects.submit', $projectId))
            ->assertStatus(422);
        $this->assertDatabaseHas('research_projects', ['id' => $projectId, 'status' => 'draft']);
    }

    public function test_researcher_can_only_add_active_researchers_to_a_project(): void
    {
        $researcher = User::factory()->create(['account_type' => 'researcher', 'status' => 'active']);
        $researcher->assignRole('researcher');
        $student = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);
        $projectId = (string) Str::uuid();
        DB::table('research_projects')->insert(['id' => $projectId, 'principal_researcher_id' => $researcher->id, 'title' => 'Projet équipe', 'reference' => 'PRJ-'.Str::random(8), 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($researcher)->post(route('researcher.projects.members', $projectId), ['user_id' => $student->id, 'role' => 'Assistant'])
            ->assertStatus(422);
        $this->assertDatabaseMissing('research_project_members', ['research_project_id' => $projectId, 'user_id' => $student->id]);
    }

    public function test_researcher_cannot_assign_a_laboratory_from_another_institution(): void
    {
        $universityA = (string) Str::uuid();
        $universityB = (string) Str::uuid();
        DB::table('universities')->insert([
            ['id' => $universityA, 'name' => 'Institution A', 'code' => 'UA-'.Str::random(5), 'country' => 'Guinée', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $universityB, 'name' => 'Institution B', 'code' => 'UB-'.Str::random(5), 'country' => 'Guinée', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $laboratory = (string) Str::uuid();
        DB::table('laboratories')->insert(['id' => $laboratory, 'university_id' => $universityB, 'name' => 'Laboratoire B', 'code' => 'LB-'.Str::random(5), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $researcher = User::factory()->create(['account_type' => 'researcher', 'status' => 'active']);
        $researcher->assignRole('researcher');
        DB::table('researcher_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $researcher->id, 'researcher_number' => 'CHR-'.Str::random(8), 'university_id' => $universityA, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($researcher)->post(route('researcher.projects.create'), ['title' => 'Projet institutionnel', 'laboratory_id' => $laboratory])
            ->assertStatus(422);
    }

    private function registrationPayload(): array
    {
        return ['first_name' => 'Ada', 'last_name' => 'Chercheuse', 'email' => 'researcher@example.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'phone' => '70000000', 'country' => 'Burkina Faso', 'research_domain' => 'Sciences', 'speciality' => 'Recherche appliquée', 'terms' => '1'];
    }
}
