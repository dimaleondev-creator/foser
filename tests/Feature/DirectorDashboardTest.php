<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DirectorDashboardService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Pages\Login as FilamentLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DirectorDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_director_dashboard_uses_real_counts_filters_and_alerts_without_personal_details(): void
    {
        [$director, $university, $program, $student, $application] = $this->context();
        DB::table('applications')->where('id', $application)->update(['status' => 'soumis', 'workflow_status' => 'submitted', 'budget' => 125000]);
        DB::table('application_awards')->insert(['id' => (string) Str::uuid(), 'application_id' => $application, 'beneficiary_id' => $student->id, 'program_id' => $program, 'amount' => 80000, 'award_date' => today(), 'decision_reference' => 'DEC-'.Str::random(6), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('financial_commitments')->insert(['id' => (string) Str::uuid(), 'application_id' => $application, 'reference' => 'ENG-'.Str::random(6), 'amount' => 100000, 'currency' => 'FCFA', 'status' => 'approved', 'committed_at' => today(), 'created_at' => now(), 'updated_at' => now()]);
        $commitment = DB::table('financial_commitments')->where('application_id', $application)->value('id');
        DB::table('disbursements')->insert(['id' => (string) Str::uuid(), 'commitment_id' => $commitment, 'reference' => 'DIS-'.Str::random(6), 'amount' => 25000, 'status' => 'execute', 'scheduled_for' => today(), 'disbursed_at' => today(), 'created_at' => now(), 'updated_at' => now()]);
        $disbursement = DB::table('disbursements')->where('commitment_id', $commitment)->value('id');
        DB::table('payment_records')->insert([
            ['id' => (string) Str::uuid(), 'disbursement_id' => $disbursement, 'provider_reference' => 'PAY-PENDING', 'amount' => 5000, 'status' => 'pending', 'paid_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'disbursement_id' => $disbursement, 'provider_reference' => 'PAY-DONE', 'amount' => 20000, 'status' => 'paid', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $projectUser = User::factory()->create(['account_type' => 'researcher', 'status' => 'active']);
        $projectUser->assignRole('researcher');
        DB::table('researcher_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $projectUser->id, 'university_id' => $university, 'researcher_number' => 'R-'.Str::random(8), 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $researchProgram = (string) Str::uuid();
        DB::table('research_programs')->insert(['id' => $researchProgram, 'program_id' => $program, 'research_area' => 'Sciences', 'created_at' => now(), 'updated_at' => now()]);
        $researchProject = (string) Str::uuid();
        DB::table('research_projects')->insert(['id' => $researchProject, 'research_program_id' => $researchProgram, 'principal_researcher_id' => $projectUser->id, 'title' => 'Projet financé réel', 'reference' => 'RP-'.Str::random(6), 'status' => 'funded', 'budget' => 40000, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('financial_commitments')->insert(['id' => (string) Str::uuid(), 'research_project_id' => $researchProject, 'reference' => 'ENG-R-'.Str::random(6), 'amount' => 40000, 'currency' => 'FCFA', 'status' => 'approved', 'committed_at' => today(), 'created_at' => now(), 'updated_at' => now()]);

        $snapshot = app(DirectorDashboardService::class)->dashboard([]);
        $this->assertSame(1, $snapshot['summary']['applications_pending']);
        $this->assertSame(0, $snapshot['summary']['applications_processing']);
        $this->assertSame(0.0, $snapshot['summary']['treatment_rate']);
        $this->assertSame(2, $snapshot['summary']['payments']);
        $this->assertSame(1, $snapshot['summary']['payments_pending']);
        $this->assertSame(1, $snapshot['summary']['payments_completed']);
        $this->assertSame(20000.0, $snapshot['summary']['amount_paid']);
        $this->assertSame(40000.0, $snapshot['summary']['research_amount']);
        $this->assertSame(3, count($snapshot['charts']['financial_comparison']));
        $this->assertSame(3, count($snapshot['charts']['treatment_performance']));
        $this->assertSame('Femmes', $snapshot['charts']['by_sex'][0]['label']);
        $this->assertSame('Conakry', $snapshot['charts']['by_region'][0]['label']);
        DB::table('applications')->where('id', $application)->update(['status' => 'evaluation', 'workflow_status' => 'evaluation']);
        $inProgressSnapshot = app(DirectorDashboardService::class)->dashboard([]);
        $this->assertSame(1, $inProgressSnapshot['summary']['applications_processing']);
        DB::table('applications')->where('id', $application)->update(['status' => 'rejete', 'workflow_status' => 'rejected']);
        $treatedSnapshot = app(DirectorDashboardService::class)->dashboard([]);
        $this->assertSame(1, $treatedSnapshot['summary']['applications_rejected']);
        $this->assertSame(100.0, $treatedSnapshot['summary']['treatment_rate']);
        DB::table('applications')->where('id', $application)->update(['status' => 'soumis', 'workflow_status' => 'submitted']);
        $this->actingAs($director)->get(route('directeur_general.dashboard'))
            ->assertOk()->assertSee('Tableau décisionnel')->assertSee('Étudiants inscrits')->assertSee('Alertes décisionnelles')
            ->assertSee('125 000')->assertSee('140 000')->assertSee('80 000')->assertSee('25 000')->assertSee('115 000')
            ->assertDontSee($student->email)->assertDontSee('INE-SECRET')->assertSee('Projets financés par programme');
        $this->get(route('directeur_general.dashboard', ['university_id' => $university, 'academic_year' => '2025-2026', 'year' => today()->year, 'sex' => 'F']))
            ->assertOk()->assertSee('125 000');
        $this->get(route('directeur_general.dashboard', ['region' => 'Région sans dossier']))->assertOk();
        $filteredDashboard = app(DirectorDashboardService::class)->dashboard(['region' => 'Région sans dossier']);
        $this->assertSame(0, $filteredDashboard['summary']['applications']);
        $researcherView = app(DirectorDashboardService::class)->dashboard(['beneficiary_type' => 'researcher']);
        $this->assertSame(0, $researcherView['summary']['applications']);
        $this->assertSame(0, $researcherView['summary']['students']);
        $this->assertSame(1, $researcherView['summary']['researchers']);
        $this->get(route('directeur_general.dashboard', ['status' => 'not-a-status']))->assertSessionHasErrors('status');
        $this->get(route('directeur_general.dashboard', ['beneficiary_type' => 'unknown']))->assertSessionHasErrors('beneficiary_type');
        $this->get(route('directeur_general.dashboard', ['sex' => 'not-a-real-value']))->assertSessionHasErrors('sex');
    }

    public function test_dashboard_and_exports_are_role_and_permission_protected(): void
    {
        $student = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);
        $student->assignRole('etudiant');
        $this->actingAs($student)->get('/director/dashboard')->assertForbidden();

        Auth::logout();
        $director = User::factory()->create(['account_type' => 'directeur_general', 'status' => 'active']);
        $limitedRole = Role::create(['name' => 'directeur_sans_export', 'guard_name' => 'web']);
        $limitedRole->givePermissionTo(Permission::findByName('reports.view', 'web'));
        $director->syncRoles([$limitedRole]);
        $this->actingAs($director)->get('/director/dashboard')->assertOk();
        $this->get('/director/dashboard/export.xlsx')->assertForbidden();
        $this->get('/director/dashboard/export.pdf')->assertForbidden();

        $director->givePermissionTo('reports.export');
        $this->get('/director/dashboard/export.xlsx')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $pdf = $this->get('/director/dashboard/export.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_director_can_sign_in_through_admin_filament_login_and_is_redirected_to_director_dashboard(): void
    {
        Auth::logout();
        $director = User::factory()->create([
            'name' => 'Directrice FOSER',
            'email' => 'directrice@example.test',
            'password' => bcrypt('Strong-password-123'),
            'account_type' => 'directeur_general',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $director->assignRole('directeur_general');

        Livewire::test(FilamentLogin::class)
            ->fillForm(['email' => $director->email, 'password' => 'Strong-password-123'])
            ->call('authenticate')
            ->assertRedirect(route('directeur_general.dashboard'));

        $this->assertAuthenticatedAs($director);
        $this->get(route('directeur_general.dashboard'))->assertOk();
    }

    private function context(): array
    {
        $director = User::factory()->create(['name' => 'Direction FOSER', 'account_type' => 'directeur_general', 'status' => 'active']);
        $director->assignRole('directeur_general');
        $university = (string) Str::uuid();
        DB::table('universities')->insert(['id' => $university, 'name' => 'Université test', 'code' => 'UNI-'.Str::random(6), 'country' => 'Guinée', 'region' => 'Conakry', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $program = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $program, 'name' => 'Bourse test', 'code' => 'PR-'.Str::random(6), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $call = (string) Str::uuid();
        DB::table('calls')->insert(['id' => $call, 'program_id' => $program, 'title' => 'Appel test', 'reference' => 'CA-'.Str::random(6), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDays(7), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $student = User::factory()->create(['name' => 'Étudiant Confidentiel', 'email' => 'private-student@example.test', 'account_type' => 'etudiant', 'status' => 'active']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'university_id' => $university, 'inee' => 'INE-SECRET', 'region' => 'Conakry', 'sex' => 'F', 'academic_year' => '2025-2026', 'created_at' => now(), 'updated_at' => now()]);
        $application = (string) Str::uuid();
        DB::table('applications')->insert(['id' => $application, 'call_id' => $call, 'program_id' => $program, 'applicant_id' => $student->id, 'reference' => 'APP-'.Str::random(6), 'status' => 'brouillon', 'workflow_status' => 'draft', 'project_title' => 'Projet confidentiel', 'created_at' => now(), 'updated_at' => now()]);

        return [$director, $university, $program, $student, $application];
    }
}
