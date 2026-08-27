<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UniversityPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');
    }

    public function test_university_students_are_isolated_and_paginated(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $otherUniversity = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active']);
        $manager->assignRole('universite');
        DB::table('university_users')->insert(['id' => (string) Str::uuid(), 'university_id' => $university, 'user_id' => $manager->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        $this->createStudent('local@example.test', $university, 'pending');
        $this->createStudent('other@example.test', $otherUniversity, 'pending');

        $response = $this->actingAs($manager)->get(route('university.students', ['q' => 'local@example.test']));

        $response->assertOk()->assertSee('local@example.test')->assertDontSee('other@example.test');
    }

    public function test_university_laboratories_are_scoped_to_the_current_university(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $otherUniversity = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active']);
        $manager->assignRole('universite');
        DB::table('university_users')->insert(['id' => (string) Str::uuid(), 'university_id' => $university, 'user_id' => $manager->id, 'role' => 'responsable', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('laboratories')->insert([
            ['id' => (string) Str::uuid(), 'university_id' => $university, 'name' => 'Laboratoire local', 'code' => 'LAB-LOCAL', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'university_id' => $otherUniversity, 'name' => 'Laboratoire externe', 'code' => 'LAB-EXTERNE', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($manager)->get(route('university.laboratories'))->assertOk()->assertSee('Laboratoire local')->assertDontSee('Laboratoire externe');
    }

    public function test_university_responsible_can_add_a_laboratory_to_its_university(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active']);
        $manager->assignRole('universite');
        DB::table('university_users')->insert(['id' => (string) Str::uuid(), 'university_id' => $university, 'user_id' => $manager->id, 'role' => 'responsable', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($manager)->post(route('university.laboratories.store'), [
            'name' => 'Laboratoire ajouté',
            'code' => 'LAB-AJOUTE',
            'description' => 'Description du laboratoire.',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('laboratories', ['university_id' => $university, 'name' => 'Laboratoire ajouté', 'code' => 'LAB-AJOUTE', 'status' => 'active']);
        $laboratoryId = DB::table('laboratories')->where('code', 'LAB-AJOUTE')->value('id');
        $this->assertDatabaseHas('audit_logs', ['event' => 'university.laboratory.created', 'auditable_id' => $laboratoryId]);
    }

    public function test_university_cannot_validate_an_application_from_another_university(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $otherUniversity = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active']);
        $manager->assignRole('universite');
        DB::table('university_users')->insert(['id' => (string) Str::uuid(), 'university_id' => $university, 'user_id' => $manager->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        $student = $this->createStudent('applicant@example.test', $otherUniversity, 'validated');
        $program = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $program, 'name' => 'Programme test', 'code' => 'P-'.Str::random(6), 'type' => 'research', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $call = (string) Str::uuid();
        DB::table('calls')->insert(['id' => $call, 'program_id' => $program, 'title' => 'Appel test', 'reference' => 'C-'.Str::random(6), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $application = (string) Str::uuid();
        DB::table('applications')->insert(['id' => $application, 'call_id' => $call, 'program_id' => $program, 'applicant_id' => $student, 'reference' => 'APP-'.Str::random(6), 'status' => 'verification', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($manager)->post(route('university.applications.validate', $application))->assertNotFound();
        $this->assertDatabaseHas('applications', ['id' => $application, 'status' => 'verification']);
    }

    public function test_university_validation_updates_status_and_records_actor_scope_and_notifications(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        DB::table('university_users')->where('university_id', $university)->where('user_id', $manager->id)->update(['role' => 'responsable', 'updated_at' => now()]);
        $student = $this->createStudent('validation@example.test', $university, 'validated');
        [$program, $call] = $this->createApplicationContext();
        $application = (string) Str::uuid();
        DB::table('applications')->insert(['id' => $application, 'call_id' => $call, 'program_id' => $program, 'applicant_id' => $student, 'reference' => 'APP-'.Str::random(6), 'status' => 'verification', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($manager)->post(route('university.applications.validate', $application))->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('applications', ['id' => $application, 'status' => 'eligible']);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application, 'changed_by' => $manager->id, 'from_status' => 'verification', 'to_status' => 'eligible']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'university.application.validated', 'auditable_id' => $application]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $manager->id, 'type' => 'university.application.validated']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $student, 'type' => 'application.status_changed']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id, 'type' => 'university.application.validated']);
    }

    public function test_university_can_export_scoped_reports(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active']);
        $manager->assignRole('universite');
        DB::table('university_users')->insert(['id' => (string) Str::uuid(), 'university_id' => $university, 'user_id' => $manager->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($manager)->get(route('university.reports.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_university_reports_page_uses_expected_statistic_keys(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active']);
        $manager->assignRole('universite');
        DB::table('university_users')->insert(['id' => (string) Str::uuid(), 'university_id' => $university, 'user_id' => $manager->id, 'role' => 'responsable', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($manager)->get(route('university.reports'))->assertOk()->assertSee('Rapports et statistiques')->assertSee('Étudiants');
    }

    public function test_university_message_has_a_foser_recipient(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active']);
        $manager->assignRole('universite');
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        DB::table('university_users')->insert(['id' => (string) Str::uuid(), 'university_id' => $university, 'user_id' => $manager->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($manager)->post(route('university.messages'), ['subject' => 'Question', 'body' => 'Bonjour FOSER'])->assertRedirect();
        $threadId = DB::table('message_threads')->latest('created_at')->value('id');
        $this->assertDatabaseHas('message_thread_users', ['thread_id' => $threadId, 'user_id' => $admin->id]);
    }

    public function test_unassigned_university_responsible_is_denied_with_clear_message(): void
    {
        $responsible = User::factory()->create(['account_type' => 'universite', 'status' => 'active']);
        $responsible->assignRole('universite');

        $this->actingAs($responsible)->get('/university')->assertForbidden()->assertSee('n’est associé à aucune université');
    }

    public function test_active_university_responsible_with_assignment_is_allowed(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $responsible = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $responsible->assignRole('universite');

        $this->actingAs($responsible)->get('/university')->assertOk();
    }

    public function test_disabled_university_responsible_is_denied(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $responsible = User::factory()->create(['account_type' => 'universite', 'status' => 'disabled', 'university_id' => $university]);
        $responsible->assignRole('universite');

        $this->actingAs($responsible)->get('/university')->assertForbidden();
    }

    public function test_global_admin_is_redirected_to_global_panel(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/university')->assertRedirect(route('filament.admin.pages.dashboard'));
    }

    public function test_unassigned_responsible_is_not_redirected_to_university_after_login(): void
    {
        $responsible = User::factory()->create(['email' => 'unassigned@example.test', 'account_type' => 'universite', 'status' => 'active']);
        $responsible->assignRole('universite');

        $this->post('/student/login', ['email' => 'unassigned@example.test', 'password' => 'password'])
            ->assertRedirect(route('student.login'))
            ->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    private function createUniversity(string $code): string
    {
        $id = (string) Str::uuid();
        DB::table('universities')->insert(['id' => $id, 'name' => 'Université '.$code, 'code' => $code, 'country' => 'Guinée', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    private function createStudent(string $email, string $universityId, string $status): int
    {
        $student = User::factory()->create(['email' => $email, 'account_type' => 'etudiant', 'status' => 'active']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'university_id' => $universityId, 'inee' => 'ETU-'.Str::random(8), 'validation_status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        return $student->id;
    }

    private function createApplicationContext(): array
    {
        $program = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $program, 'name' => 'Programme validation', 'code' => 'P-'.Str::random(6), 'type' => 'research', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $call = (string) Str::uuid();
        DB::table('calls')->insert(['id' => $call, 'program_id' => $program, 'title' => 'Appel validation', 'reference' => 'C-'.Str::random(6), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        return [$program, $call];
    }
}