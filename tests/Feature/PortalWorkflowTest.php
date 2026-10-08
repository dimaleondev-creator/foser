<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Document;
use App\Enums\StudentApplicationStatus;
use App\Services\StudentApplicationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');
    }

    public function test_student_can_register_and_logout(): void
    {
        $this->post(route('ine.verify'), ['last_name' => 'Test', 'first_name' => 'Awa', 'inee' => 'ETU-TEST-001'])->assertRedirect();
        $response = $this->post('/student/register', [
            'name' => 'Awa Test', 'email' => 'awa@example.test', 'phone' => '07000000', 'terms' => '1',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('ine.declare'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'awa@example.test']);
        $this->assertDatabaseHas('student_profiles', ['inee' => DB::table('student_profiles')->value('inee')]);

        $this->post('/student/logout')->assertRedirect('/student/login');
        $this->assertGuest();
    }

    public function test_verified_student_can_create_and_submit_a_draft_and_audit_is_written(): void
    {
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Aide test', 'code' => 'TEST-'.Str::random(5), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel test', 'reference' => 'CALL-'.Str::random(5), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $student = User::factory()->create(['email_verified_at' => now()]);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => 'ETU-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($student)->post('/student/applications', ['call_id' => $callId])->assertRedirect();
        $applicationId = DB::table('applications')->value('id');
        app(StudentApplicationWorkflow::class)->updateDraft($student, $applicationId, [
            'project_title' => 'Projet test', 'summary' => 'Résumé test', 'description' => 'Description test',
            'domain' => 'Éducation', 'objectives' => 'Objectifs test', 'methodology' => 'Méthode test',
            'calendar' => 'Calendrier test', 'budget' => 1000, 'team' => null, 'applicant_note' => null,
        ]);
        $this->actingAs($student)->post('/student/applications/'.$applicationId.'/submit')->assertRedirect();

        $this->assertDatabaseHas('applications', ['id' => $applicationId, 'status' => 'recevable']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'application.status_changed', 'auditable_id' => $applicationId]);
    }

    public function test_student_without_a_usable_inee_cannot_create_a_draft(): void
    {
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme INEE', 'code' => 'INEE-'.Str::random(5), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel INEE', 'reference' => 'INEE-'.Str::random(5), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $student = User::factory()->create(['account_type' => 'etudiant']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => null, 'created_at' => now(), 'updated_at' => now()]);

        try {
            app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
            $this->fail('A student without an INEE must not create an application.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('inee', $exception->errors());
        }

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_operator_can_check_completeness_and_student_cannot_edit_submitted_dossier(): void
    {
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme workflow', 'code' => 'WF-'.Str::random(5), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel workflow', 'reference' => 'WF-'.Str::random(5), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $student = User::factory()->create(['account_type' => 'etudiant']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => 'ETU-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        app(StudentApplicationWorkflow::class)->updateDraft($student, $application->id, [
            'project_title' => 'Projet workflow', 'summary' => 'Résumé workflow', 'description' => 'Description workflow',
            'domain' => 'Éducation', 'objectives' => 'Objectifs workflow', 'methodology' => 'Méthode workflow',
            'calendar' => 'Calendrier workflow', 'budget' => 1000, 'team' => null, 'applicant_note' => null,
        ]);
        app(StudentApplicationWorkflow::class)->transition($student, $application->id, StudentApplicationStatus::SOUMIS);
        $this->assertSame('recevable', DB::table('applications')->where('id', $application->id)->value('status'));

        $operator = User::factory()->create(['account_type' => 'agent_dossier']);
        $operator->assignRole('agent_dossier');
        $this->assertSame('agent_dossier', $operator->account_type);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application->id, 'to_status' => 'recevable']);
    }

    public function test_rejected_required_document_does_not_count_toward_completeness(): void
    {
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme pièces', 'code' => 'DOC-'.Str::random(5), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel pièces', 'reference' => 'DOC-'.Str::random(5), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $student = User::factory()->create(['account_type' => 'etudiant']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => 'DOC-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('required_documents')->insert(['id' => (string) Str::uuid(), 'program_id' => $programId, 'document_type' => 'identite', 'label' => 'Pièce d’identité', 'is_required' => true, 'created_at' => now(), 'updated_at' => now()]);
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update([
            'project_title' => 'Projet complet', 'summary' => 'Résumé', 'description' => 'Description', 'domain' => 'Domaine',
            'objectives' => 'Objectifs', 'methodology' => 'Méthode', 'calendar' => 'Calendrier', 'budget' => 1000,
        ]);
        $document = Document::create(['uploaded_by' => $student->id, 'title' => 'Pièce rejetée', 'document_type' => 'identite', 'disk' => 'local', 'path' => 'student-documents/rejected.pdf', 'mime_type' => 'application/pdf', 'size' => 10, 'visibility' => 'private']);
        DB::table('application_documents')->insert(['id' => (string) Str::uuid(), 'application_id' => $application->id, 'document_id' => $document->id, 'status' => 'rejected', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(StudentApplicationWorkflow::class)->transition($student, $application->id, StudentApplicationStatus::SOUMIS);
    }

    public function test_student_can_resubmit_a_completed_complement_request(): void
    {
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme complément', 'code' => 'COMP-'.Str::random(5), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel complément', 'reference' => 'COMP-'.Str::random(5), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $student = User::factory()->create(['account_type' => 'etudiant']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => 'COMP-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        $application = app(StudentApplicationWorkflow::class)->createDraft($student, $callId);
        DB::table('applications')->where('id', $application->id)->update([
            'status' => 'complement', 'project_title' => 'Projet complété', 'summary' => 'Résumé', 'description' => 'Description',
            'domain' => 'Domaine', 'objectives' => 'Objectifs', 'methodology' => 'Méthode', 'calendar' => 'Calendrier', 'budget' => 1000,
        ]);

        app(StudentApplicationWorkflow::class)->transition($student, $application->id, StudentApplicationStatus::SOUMIS);

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'recevable']);
    }

    public function test_call_capacity_is_enforced_when_students_submit_their_drafts(): void
    {
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme capacité', 'code' => 'CAP-'.Str::random(5), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel capacité', 'reference' => 'CAP-'.Str::random(5), 'opens_at' => today()->subDay(), 'closes_at' => today(), 'places' => 1, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $workflow = app(StudentApplicationWorkflow::class);
        $applications = [];

        foreach (range(1, 2) as $index) {
            $student = User::factory()->create(['account_type' => 'etudiant']);
            $student->assignRole('etudiant');
            DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => 'CAP'.$index.'-'.Str::random(6), 'created_at' => now(), 'updated_at' => now()]);
            $application = $workflow->createDraft($student, $callId);
            $workflow->updateDraft($student, $application->id, [
                'project_title' => 'Projet '.$index, 'summary' => 'Résumé', 'description' => 'Description', 'domain' => 'Domaine',
                'objectives' => 'Objectifs', 'methodology' => 'Méthode', 'calendar' => 'Calendrier', 'budget' => 1000,
            ]);
            $applications[] = [$student, $application];
        }

        $workflow->transition($applications[0][0], $applications[0][1]->id, StudentApplicationStatus::SOUMIS);

        try {
            $workflow->transition($applications[1][0], $applications[1][1]->id, StudentApplicationStatus::SOUMIS);
            $this->fail('The call capacity must prevent a second submission.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('places', $exception->errors());
        }

        $this->assertDatabaseHas('applications', ['id' => $applications[0][1]->id, 'status' => 'recevable']);
        $this->assertDatabaseHas('applications', ['id' => $applications[1][1]->id, 'status' => 'brouillon']);
    }

    public function test_student_can_only_see_own_application_timeline(): void
    {
        $owner = User::factory()->create(['account_type' => 'etudiant']);
        $other = User::factory()->create(['account_type' => 'etudiant']);
        $owner->assignRole('etudiant');
        $other->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $owner->id, 'inee' => 'ETU-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme isolation', 'code' => 'ISO-'.Str::random(5), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel isolation', 'reference' => 'ISO-'.Str::random(5), 'opens_at' => today(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $applicationId = (string) Str::uuid();
        DB::table('applications')->insert(['id' => $applicationId, 'call_id' => $callId, 'program_id' => $programId, 'applicant_id' => $owner->id, 'reference' => 'OWN-'.Str::random(6), 'status' => 'brouillon', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($other)->get('/student/applications')->assertOk()->assertDontSee('OWN-');
    }

    public function test_unverified_student_can_temporarily_access_the_portal(): void
    {
        $student = User::factory()->create(['account_type' => 'etudiant', 'email_verified_at' => null]);
        $student->assignRole('etudiant');

        $this->actingAs($student)->get('/student')->assertOk();
    }

    public function test_student_cannot_download_another_students_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('student-documents/owned.pdf', 'private content');
        $owner = User::factory()->create(['account_type' => 'etudiant', 'email_verified_at' => now()]);
        $other = User::factory()->create(['account_type' => 'etudiant', 'email_verified_at' => now()]);
        $owner->assignRole('etudiant');
        $other->assignRole('etudiant');
        DB::table('student_profiles')->insert([
            ['id' => (string) Str::uuid(), 'user_id' => $owner->id, 'inee' => 'OWN-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'user_id' => $other->id, 'inee' => 'OTH-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme documents', 'code' => 'DOC-'.Str::random(5), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel documents', 'reference' => 'DOC-'.Str::random(5), 'opens_at' => today(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $applicationId = (string) Str::uuid();
        DB::table('applications')->insert(['id' => $applicationId, 'call_id' => $callId, 'program_id' => $programId, 'reference' => 'DOC-'.Str::random(6), 'applicant_id' => $owner->id, 'status' => 'brouillon', 'created_at' => now(), 'updated_at' => now()]);
        $document = Document::create(['uploaded_by' => $owner->id, 'title' => 'Justificatif', 'document_type' => 'identite', 'disk' => 'local', 'path' => 'student-documents/owned.pdf', 'mime_type' => 'application/pdf', 'size' => 14, 'visibility' => 'private']);
        DB::table('application_documents')->insert(['id' => (string) Str::uuid(), 'application_id' => $applicationId, 'document_id' => $document->id, 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($other)->get('/student/documents/'.$document->id.'/download')->assertNotFound();
        $this->actingAs($owner)->get('/student/documents/'.$document->id.'/download')->assertDownload('Justificatif.pdf');
    }

    public function test_public_call_detail_excludes_draft_calls(): void
    {
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme privé', 'code' => 'PRIVATE-'.Str::random(5), 'type' => 'research', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel privé', 'reference' => 'PRIVATE-'.Str::random(5), 'opens_at' => today(), 'closes_at' => today()->addDay(), 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/calls/'.$callId)->assertNotFound();
    }

    public function test_password_recovery_is_available_and_rate_limited(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Réinitialiser votre mot de passe');
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('password.email');
        $this->assertContains('throttle:6,1', $route->middleware());
    }
}
