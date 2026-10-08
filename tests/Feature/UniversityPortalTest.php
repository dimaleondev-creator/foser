<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function test_university_student_validation_is_scoped_audited_and_notifies_the_student(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $otherUniversity = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');
        $localStudent = $this->createStudent('pending-local@example.test', $university, 'pending');
        $foreignStudent = $this->createStudent('pending-foreign@example.test', $otherUniversity, 'pending');

        $this->actingAs($manager)->post(route('university.students.validate', $foreignStudent))->assertNotFound();
        $this->post(route('university.students.validate', $localStudent))->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('student_profiles', ['user_id' => $localStudent, 'university_id' => $university, 'validation_status' => 'validated']);
        $this->assertDatabaseHas('student_profiles', ['user_id' => $foreignStudent, 'university_id' => $otherUniversity, 'validation_status' => 'pending']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'university.student.validated', 'user_id' => $manager->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $localStudent, 'type' => 'student.university_profile_validated']);
    }

    public function test_university_cannot_habilitate_a_global_foser_account(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active']);
        $manager->assignRole('universite');
        DB::table('university_users')->insert(['id' => (string) Str::uuid(), 'university_id' => $university, 'user_id' => $manager->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');

        $this->actingAs($manager)->post(route('university.users.add'), ['user_id' => $admin->id, 'role' => 'admin_universite'])->assertStatus(422);
        $this->assertDatabaseMissing('university_users', ['university_id' => $university, 'user_id' => $admin->id]);
    }

    public function test_university_users_page_is_scoped_to_the_current_university(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $otherUniversity = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');
        $localUser = User::factory()->create(['name' => 'Compte local', 'email' => 'local-staff@example.test', 'account_type' => 'universite', 'status' => 'active']);
        $foreignUser = User::factory()->create(['name' => 'Compte externe', 'email' => 'foreign-staff@example.test', 'account_type' => 'universite', 'status' => 'active']);
        $availableUser = User::factory()->create(['name' => 'Compte disponible', 'email' => 'available-staff@example.test', 'account_type' => 'universite', 'status' => 'active']);
        DB::table('university_users')->insert([
            ['id' => (string) Str::uuid(), 'university_id' => $university, 'user_id' => $localUser->id, 'role' => 'validateur', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'university_id' => $otherUniversity, 'user_id' => $foreignUser->id, 'role' => 'validateur', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($manager)->get(route('university.users'))->assertOk()
            ->assertSee('local-staff@example.test')
            ->assertDontSee('foreign-staff@example.test')
            ->assertSee('available-staff@example.test');
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
        DB::table('applications')->insert(['id' => $application, 'call_id' => $call, 'program_id' => $program, 'applicant_id' => $student, 'reference' => 'APP-'.Str::random(6), 'status' => 'verification', 'project_title' => 'Projet validable', 'summary' => 'Résumé', 'description' => 'Description', 'domain' => 'Recherche', 'objectives' => 'Objectifs', 'methodology' => 'Méthode', 'calendar' => 'Calendrier', 'budget' => 1000, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($manager)->post(route('university.applications.validate', $application))->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('applications', ['id' => $application, 'status' => 'eligible']);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application, 'changed_by' => $manager->id, 'from_status' => 'verification', 'to_status' => 'eligible']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'university.application.validated', 'auditable_id' => $application]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $manager->id, 'type' => 'university.application.validated']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $student, 'type' => 'application.status_changed']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id, 'type' => 'university.application.validated']);
    }

    public function test_university_cannot_validate_an_incomplete_application(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');
        $student = $this->createStudent('incomplete@example.test', $university, 'validated');
        [$program, $call] = $this->createApplicationContext();
        $application = (string) Str::uuid();
        DB::table('applications')->insert(['id' => $application, 'call_id' => $call, 'program_id' => $program, 'applicant_id' => $student, 'reference' => 'APP-'.Str::random(6), 'status' => 'verification', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($manager)->post(route('university.applications.validate', $application))->assertSessionHasErrors('application');

        $this->assertDatabaseHas('applications', ['id' => $application, 'status' => 'verification']);
    }

    public function test_university_can_reject_or_request_correction_with_audited_reason_and_cannot_touch_foreign_applications(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $otherUniversity = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');
        $student = $this->createStudent('decision@example.test', $university, 'validated');
        $correctionStudent = $this->createStudent('correction@example.test', $university, 'validated');
        $foreignStudent = $this->createStudent('foreign-decision@example.test', $otherUniversity, 'validated');
        [$program, $call] = $this->createApplicationContext();
        $rejectedApplication = $this->insertApplication($student, $program, $call, 'verification');
        $correctionApplication = $this->insertApplication($correctionStudent, $program, $call, 'soumis');
        $foreignApplication = $this->insertApplication($foreignStudent, $program, $call, 'verification');

        $this->actingAs($manager)->post(route('university.applications.reject', $rejectedApplication))->assertSessionHasErrors('reason');
        $this->post(route('university.applications.reject', $foreignApplication), ['reason' => 'Motif hors établissement'])->assertNotFound();
        $this->post(route('university.applications.reject', $rejectedApplication), ['reason' => 'Documents falsifiés'])->assertRedirect()->assertSessionHas('status');
        $this->post(route('university.applications.correction', $correctionApplication), ['reason' => 'Veuillez corriger le relevé de notes.'])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('applications', ['id' => $rejectedApplication, 'status' => 'rejected']);
        $this->assertDatabaseHas('applications', ['id' => $correctionApplication, 'status' => 'complement']);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $rejectedApplication, 'changed_by' => $manager->id, 'reason' => 'Documents falsifiés']);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $correctionApplication, 'changed_by' => $manager->id, 'reason' => 'Veuillez corriger le relevé de notes.']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'university.application.rejected', 'auditable_id' => $rejectedApplication]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'university.application.correction_requested', 'auditable_id' => $correctionApplication]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $student, 'type' => 'application.status_changed']);
    }

    public function test_university_application_review_and_document_download_are_scoped_to_its_students(): void
    {
        Storage::fake('local');
        $university = $this->createUniversity('U-'.Str::random(5));
        $otherUniversity = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');
        $localStudent = $this->createStudent('local-review@example.test', $university, 'validated');
        $foreignStudent = $this->createStudent('foreign-review@example.test', $otherUniversity, 'validated');
        [$program, $call] = $this->createApplicationContext();
        $localApplication = $this->insertApplication($localStudent, $program, $call, 'verification');
        $foreignApplication = $this->insertApplication($foreignStudent, $program, $call, 'verification');
        $documentId = $this->attachDocument($localApplication, $localStudent, 'student-documents/local.pdf');
        $foreignDocumentId = $this->attachDocument($foreignApplication, $foreignStudent, 'student-documents/foreign.pdf');
        Storage::disk('local')->put('student-documents/local.pdf', 'local document');
        Storage::disk('local')->put('student-documents/foreign.pdf', 'foreign document');

        $this->actingAs($manager)->get(route('university.applications.show', $localApplication))
            ->assertOk()->assertSee('local-review@example.test')->assertSee('Pièces justificatives')->assertSee('Historique des validations');
        $this->get(route('university.applications.show', $foreignApplication))->assertNotFound();
        $this->get(route('university.applications.documents.download', [$localApplication, $documentId]))->assertOk();
        $this->get(route('university.applications.documents.download', [$foreignApplication, $foreignDocumentId]))->assertNotFound();
    }

    public function test_university_import_page_offers_xlsx_template_and_preview_validates_headers(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');
        $this->actingAs($manager)->get(route('university.imports'))
            ->assertOk()->assertSee('Télécharger le modèle Excel')->assertSee('Prévisualiser');
        $this->get(route('university.imports.template'))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('students.csv', "inee,name,email\nINE-001,Étudiant,student@example.test\n");
        $this->post(route('university.imports.students.preview'), ['file' => $file])
            ->assertOk()->assertSee('INE-001')->assertSee('student@example.test');
        $invalid = \Illuminate\Http\UploadedFile::fake()->createWithContent('invalid.csv', "name,email\nÉtudiant,student@example.test\n");
        $this->post(route('university.imports.students.preview'), ['file' => $invalid])->assertSessionHasErrors('file');
    }

    public function test_university_import_creates_only_valid_students_and_audits_row_errors(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
            'students.csv',
            "inee,name,email,program,academic_year\nINE-IMPORT-1,Étudiant importé,imported@example.test,Informatique,2026-2027\nINE-IMPORT-2,Email invalide,invalid-email,Mathématiques,2026-2027\n"
        );

        $this->actingAs($manager)->post(route('university.imports.students'), ['file' => $file])
            ->assertOk()->assertSee('Étudiant importé')->assertSee('Adresse email invalide.');

        $importedUser = User::where('email', 'imported@example.test')->firstOrFail();
        $this->assertDatabaseHas('student_profiles', ['user_id' => $importedUser->id, 'university_id' => $university, 'inee' => 'INE-IMPORT-1', 'program' => 'Informatique', 'academic_year' => '2026-2027']);
        $import = DB::table('university_imports')->where('university_id', $university)->first();
        $this->assertSame(1, $import->imported_count);
        $this->assertSame(1, $import->error_count);
        $this->assertDatabaseHas('audit_logs', ['event' => 'university.students.imported', 'auditable_id' => $import->id]);
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
        $this->get(route('university.messages.index'))->assertOk()->assertSee('Bonjour FOSER')->assertSee('Répondre');
        $this->post(route('university.messages.reply', $threadId), ['body' => 'Réponse du responsable'])->assertRedirect();
        $this->assertDatabaseHas('messages', ['thread_id' => $threadId, 'sender_id' => $manager->id, 'body' => 'Réponse du responsable']);
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

    public function test_university_dashboard_shows_real_scoped_activity_and_empty_states(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $otherUniversity = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');
        $universityName = DB::table('universities')->where('id', $university)->value('name');
        $localStudent = $this->createStudent('dashboard-local@example.test', $university, 'pending');
        $foreignStudent = $this->createStudent('dashboard-foreign@example.test', $otherUniversity, 'validated');
        $localStudentName = User::findOrFail($localStudent)->name;
        [$program, $call] = $this->createApplicationContext();
        $this->insertApplication($localStudent, $program, $call, 'soumis');
        $foreignApplication = $this->insertApplication($foreignStudent, $program, $call, 'eligible');

        $this->actingAs($manager)->get(route('university.dashboard'))->assertOk()
            ->assertSee($universityName)
            ->assertSee('Dossiers récents')->assertSee($localStudentName)
            ->assertDontSee('dashboard-foreign@example.test')
            ->assertSee('Import des étudiants')->assertSee('Appels ouverts');
        $this->get(route('university.applications.show', $foreignApplication))->assertNotFound();
    }

    public function test_university_dashboard_explains_empty_student_application_and_message_states(): void
    {
        $university = $this->createUniversity('U-'.Str::random(5));
        $manager = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'university_id' => $university]);
        $manager->assignRole('universite');

        $this->actingAs($manager)->get(route('university.dashboard'))->assertOk()
            ->assertSee('Aucun dossier étudiant reçu pour le moment.')
            ->assertSee('Aucun étudiant n’est encore rattaché à votre université.')
            ->assertSee('Aucun nouveau message reçu.')
            ->assertSee('Aucun import enregistré.');
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

    private function insertApplication(int $student, string $program, string $call, string $status): string
    {
        $id = (string) Str::uuid();
        DB::table('applications')->insert(['id' => $id, 'call_id' => $call, 'program_id' => $program, 'applicant_id' => $student, 'reference' => 'APP-'.Str::random(6), 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    private function attachDocument(string $application, int $student, string $path): string
    {
        $documentId = (string) Str::uuid();
        DB::table('documents')->insert(['id' => $documentId, 'uploaded_by' => $student, 'title' => 'Pièce étudiant', 'document_type' => 'identite', 'disk' => 'local', 'path' => $path, 'mime_type' => 'application/pdf', 'size' => 14, 'visibility' => 'private', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('application_documents')->insert(['id' => (string) Str::uuid(), 'application_id' => $application, 'document_id' => $documentId, 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);
        return $documentId;
    }
}