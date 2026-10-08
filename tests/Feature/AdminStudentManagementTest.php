<?php

namespace Tests\Feature;

use App\Filament\Resources\Students\StudentResource;
use App\Models\User;
use App\Services\AdminStudentDataAccessService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class AdminStudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_resource_is_limited_to_students_with_profiles(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        $student = $this->createStudent('student-in-scope@example.test');
        $studentWithoutProfile = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);
        $researcher = User::factory()->create(['account_type' => 'chercheur', 'status' => 'active']);
        $researcher->assignRole('chercheur');
        $this->actingAs($admin);

        $ids = StudentResource::getEloquentQuery()->pluck('users.id')->all();

        $this->assertSame([$student->id], $ids);
        $this->assertNotContains($studentWithoutProfile->id, $ids);
        $this->assertNotContains($researcher->id, $ids);
    }

    public function test_student_cannot_access_the_admin_student_resource(): void
    {
        $student = $this->createStudent('no-admin-access@example.test');

        $this->actingAs($student)->get('/admin/students')->assertForbidden();
    }

    public function test_general_students_api_does_not_expose_sensitive_profile_fields(): void
    {
        $student = $this->createStudent('api-private-student@example.test', [
            'national_id' => 'CNIB-API-PRIVATE',
            'nip' => 'NIP-API-PRIVATE',
            'father_first_name' => 'PARENT-API-PRIVATE',
        ]);
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/students/'.$student->id)->assertOk()
            ->assertJsonMissingPath('data.national_id')
            ->assertJsonMissingPath('data.nip')
            ->assertJsonMissingPath('data.father_first_name')
            ->assertJsonMissingPath('data.mother_first_name');
    }

    public function test_filament_student_table_renders_students_and_excludes_other_accounts(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        $student = $this->createStudent('visible-student@example.test');
        $researcher = User::factory()->create(['account_type' => 'chercheur', 'status' => 'active']);
        $researcher->assignRole('chercheur');
        $this->actingAs($admin);

        Livewire::test(\App\Filament\Resources\Students\Pages\ListStudents::class)
            ->assertCanSeeTableRecords([$student])
            ->assertCanNotSeeTableRecords([$researcher]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => 'students.list.viewed',
        ]);
    }

    public function test_student_record_hides_sensitive_identifiers_and_parent_data_from_finance_role(): void
    {
        $student = $this->createStudent('private-student@example.test', [
            'national_id' => 'CNIB-PRIVATE-RECORD',
            'nip' => 'NIP-PRIVATE-RECORD',
            'father_first_name' => 'PARENT-PRIVATE-RECORD',
        ]);
        $financeAgent = User::factory()->create(['account_type' => 'agent_finance', 'status' => 'active']);
        $financeAgent->assignRole('agent_finance');
        $this->actingAs($financeAgent);

        $page = Livewire::test(\App\Filament\Resources\Students\Pages\ViewStudent::class, ['record' => $student->id])
            ->assertSee('Informations personnelles')
            ->assertSee('Téléphone')
            ->assertDontSee('CNIB-PRIVATE-RECORD')
            ->assertDontSee('NIP-PRIVATE-RECORD')
            ->assertDontSee('PARENT-PRIVATE-RECORD');
        $profileAttributes = $page->instance()->getRecord()->studentProfile->getAttributes();
        $this->assertArrayNotHasKey('national_id', $profileAttributes);
        $this->assertArrayNotHasKey('nip', $profileAttributes);
        $this->assertArrayNotHasKey('father_first_name', $profileAttributes);
    }

    public function test_authorized_admin_can_edit_student_profile_and_audit_only_field_names(): void
    {
        $student = $this->createStudent('editable-student@example.test', ['phone' => '+224600000001']);
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(\App\Filament\Resources\Students\Pages\ViewStudent::class, ['record' => $student->id])
            ->callAction('editStudent', data: [
                'name' => 'Étudiant Modifié',
                'email' => $student->email,
                'phone' => '+224600000099',
                'other_phone' => null,
                'address' => 'Adresse privée mise à jour',
                'region' => 'Conakry',
                'faculty' => 'Faculté test',
                'program' => 'Informatique',
                'study_level' => 'Licence',
                'academic_year' => '2026-2027',
                'national_id' => 'CNIB-MODIFIED-PRIVATE',
                'nip' => 'NIP-MODIFIED-PRIVATE',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('users', ['id' => $student->id, 'name' => 'Étudiant Modifié']);
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'phone' => '+224600000099', 'national_id' => 'CNIB-MODIFIED-PRIVATE', 'nip' => 'NIP-MODIFIED-PRIVATE']);
        $audit = DB::table('audit_logs')->where('event', 'students.profile.updated')->latest('created_at')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('+224600000099', $audit->new_values ?? '');
        $this->assertStringNotContainsString('CNIB-MODIFIED-PRIVATE', $audit->new_values ?? '');
        $this->assertStringNotContainsString('Adresse privée mise à jour', $audit->new_values ?? '');
    }

    public function test_standard_student_export_is_permission_gated_and_excludes_sensitive_columns(): void
    {
        $student = $this->createStudent('export-student@example.test', [
            'phone' => '+224600000123',
            'national_id' => 'CNIB-EXPORT-PRIVATE',
            'nip' => 'NIP-EXPORT-PRIVATE',
            'father_first_name' => 'PARENT-EXPORT-PRIVATE',
        ]);
        $profile = DB::table('student_profiles')->where('user_id', $student->id)->first();
        $financeAgent = User::factory()->create(['account_type' => 'agent_finance', 'status' => 'active']);
        $financeAgent->assignRole('agent_finance');
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($financeAgent);
        Livewire::test(\App\Filament\Resources\Students\Pages\ListStudents::class)
            ->assertActionHidden('exportStudents');

        $this->actingAs($admin);
        Livewire::test(\App\Filament\Resources\Students\Pages\ListStudents::class)
            ->callAction('exportStudents')
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('etudiants-'.now()->format('Y-m-d').'.csv')
            ->assertFileDownloaded('etudiants-'.now()->format('Y-m-d').'.csv', $this->expectedStudentCsv($student, $profile));

        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'event' => 'students.exported']);
    }

    public function test_sensitive_identifiers_require_permission_and_are_audited_without_values(): void
    {
        $student = $this->createStudent('sensitive-student@example.test', [
            'national_id' => 'CNIB-DO-NOT-LOG',
            'nip' => 'NIP-DO-NOT-LOG',
        ]);
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        $financeAgent = User::factory()->create(['account_type' => 'agent_finance', 'status' => 'active']);
        $financeAgent->assignRole('agent_finance');
        $this->actingAs($financeAgent);

        try {
            app(AdminStudentDataAccessService::class)->sensitiveIdentifiers($student);
            $this->fail('A finance agent must not read student CNIB or NIP without the dedicated permission.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('audit_logs', ['event' => 'students.national_id.viewed']);
        }

        $this->actingAs($admin);
        $identifiers = app(AdminStudentDataAccessService::class)->sensitiveIdentifiers($student);

        $this->assertSame('CNIB-DO-NOT-LOG', $identifiers['national_id']);
        $this->assertSame('NIP-DO-NOT-LOG', $identifiers['nip']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'event' => 'students.national_id.viewed']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'event' => 'students.nip.viewed']);
        $auditValues = DB::table('audit_logs')->where('user_id', $admin->id)->whereIn('event', ['students.national_id.viewed', 'students.nip.viewed'])->pluck('new_values')->implode(' ');
        $this->assertStringNotContainsString('CNIB-DO-NOT-LOG', $auditValues);
        $this->assertStringNotContainsString('NIP-DO-NOT-LOG', $auditValues);
    }

    public function test_parent_information_requires_its_permission_and_authorized_access_is_audited(): void
    {
        $student = $this->createStudent('parent-student@example.test', ['father_first_name' => 'Parent Confidential']);
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        $financeAgent = User::factory()->create(['account_type' => 'agent_finance', 'status' => 'active']);
        $financeAgent->assignRole('agent_finance');
        $this->actingAs($financeAgent);

        try {
            app(AdminStudentDataAccessService::class)->parentInformation($student);
            $this->fail('A finance agent must not read parent information.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('audit_logs', ['event' => 'students.parent_information.viewed']);
        }

        $this->actingAs($admin);
        $parents = app(AdminStudentDataAccessService::class)->parentInformation($student);

        $this->assertSame('Parent Confidential', $parents['father_first_name']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => 'students.parent_information.viewed',
            'auditable_id' => DB::table('student_profiles')->where('user_id', $student->id)->value('id'),
        ]);
    }

    private function createStudent(string $email, array $profile = []): User
    {
        $student = User::factory()->create(['email' => $email, 'account_type' => 'etudiant', 'status' => 'active']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(array_merge([
            'id' => (string) Str::uuid(),
            'user_id' => $student->id,
            'inee' => 'INEE-'.Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ], $profile));

        return $student;
    }

    private function expectedStudentCsv(User $student, object $profile): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Nom', 'Email', 'INEE', 'Téléphone', 'Université', 'Filière', 'Niveau', 'Année académique', 'Statut', 'Inscrit le']);
        fputcsv($stream, [
            $student->name,
            $student->email,
            $profile->inee,
            $profile->phone,
            null,
            $profile->program,
            $profile->study_level,
            $profile->academic_year,
            $student->status,
            $student->created_at?->toDateString(),
        ]);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}