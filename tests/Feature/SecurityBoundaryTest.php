<?php

namespace Tests\Feature;

use App\Filament\Widgets\FinancialComparisonChart;
use App\Filament\Widgets\FinancialStatsOverview;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_university_and_partner_accounts_cannot_enter_the_global_filament_panel(): void
    {
        $university = User::factory()->create(['account_type' => 'universite', 'status' => 'active', 'email_verified_at' => now()]);
        $university->assignRole('universite');
        $partner = User::factory()->create(['account_type' => 'partenaire', 'status' => 'active', 'email_verified_at' => now()]);
        $partner->assignRole('partenaire');

        $this->actingAs($university)->get('/admin/users')->assertForbidden();
        $this->actingAs($partner)->get('/admin/contact-messages')->assertForbidden();
        $this->actingAs($partner)->get('/admin/newsletter-subscribers')->assertForbidden();
        $this->actingAs($partner)->get('/admin/newsletter-campaigns')->assertForbidden();
    }

    public function test_director_can_view_admin_evaluations_without_validation_permission(): void
    {
        $director = User::factory()->create([
            'account_type' => 'directeur_general',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $director->assignRole('directeur_general');

        $this->assertTrue($director->can('evaluations.view'));
        $this->assertFalse($director->can('evaluations.validate'));

        $this->actingAs($director)->get('/admin/evaluations')->assertOk();
    }

    public function test_communication_private_permission_is_limited_to_authorized_staff(): void
    {
        $partner = User::factory()->create(['account_type' => 'partenaire']);
        $partner->assignRole('partenaire');
        $this->assertFalse($partner->can('communication.private.view'));

        $editor = User::factory()->create(['account_type' => 'agent_communication']);
        $editor->assignRole('agent_communication');
        $this->assertTrue($editor->can('communication.private.view'));
    }

    public function test_financial_dashboard_widgets_require_finance_permission(): void
    {
        $editor = User::factory()->create(['account_type' => 'agent_communication']);
        $editor->assignRole('agent_communication');
        $this->actingAs($editor);
        $this->assertFalse(FinancialStatsOverview::canView());
        $this->assertFalse(FinancialComparisonChart::canView());

        $financeUser = User::factory()->create(['account_type' => 'agent_finance']);
        $financeUser->assignRole('agent_finance');
        $this->actingAs($financeUser);
        $this->assertTrue(FinancialStatsOverview::canView());
        $this->assertTrue(FinancialComparisonChart::canView());
    }

    public function test_password_reset_response_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.test']);
        $message = 'Si un compte correspond à cette adresse, un lien de réinitialisation sera envoyé.';

        $known = $this->post('/forgot-password', ['email' => 'known@example.test'])->assertRedirect();
        $unknown = $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect();

        $this->assertSame($message, $known->getSession()->get('status'));
        $this->assertSame($message, $unknown->getSession()->get('status'));
    }

    public function test_student_cannot_set_application_workflow_status_in_update_payload(): void
    {
        $student = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);
        $student->assignRole('etudiant');
        $programId = (string) \Illuminate\Support\Str::uuid();
        $callId = (string) \Illuminate\Support\Str::uuid();
        $applicationId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('programs')->insert([
            'id' => $programId, 'name' => 'Test statut', 'code' => 'SEC-'.uniqid(), 'type' => 'education',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('calls')->insert([
            'id' => $callId, 'program_id' => $programId, 'title' => 'Appel statut', 'reference' => 'SEC-'.uniqid(),
            'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('applications')->insert([
            'id' => $applicationId, 'call_id' => $callId, 'program_id' => $programId, 'applicant_id' => $student->id,
            'reference' => 'SEC-'.uniqid(), 'status' => 'brouillon', 'workflow_status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student)->put('/student/applications/'.$applicationId, [
            'project_title' => 'Titre', 'summary' => 'Résumé', 'description' => 'Description',
            'domain' => 'Domaine', 'objectives' => 'Objectifs', 'methodology' => 'Méthode',
            'calendar' => 'Calendrier', 'budget' => 0, 'status' => 'paye', 'workflow_status' => 'disbursed',
        ])->assertRedirect();

        $this->assertDatabaseHas('applications', ['id' => $applicationId, 'status' => 'brouillon', 'workflow_status' => 'draft']);
    }

    public function test_staff_cannot_bypass_filament_mfa_through_student_login_for_admin_routes(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test', 'password' => bcrypt('Strong-password-123'),
            'account_type' => 'admin', 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        $this->post('/student/login', ['email' => $admin->email, 'password' => 'Strong-password-123'])
            ->assertSessionHasErrors(['login']);
        $this->assertGuest();
        $this->post('/admin/applications/not-a-real-id/commit', ['amount' => 100])
            ->assertRedirect();
    }

    public function test_student_profile_audit_redacts_personal_identifiers_and_contact_data(): void
    {
        $student = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);
        $student->assignRole('etudiant');
        \Illuminate\Support\Facades\DB::table('student_profiles')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $student->id,
            'inee' => 'INEE-PRIVATE-001', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student)->put('/student/profile', [
            'phone' => '+224620000000', 'date_of_birth' => '2000-01-02', 'birth_place' => 'Conakry',
            'national_id' => 'NID-SECRET-1', 'address' => 'Adresse privée', 'nationality' => 'Guinéenne',
        ])->assertRedirect();

        $audit = \Illuminate\Support\Facades\DB::table('audit_logs')->where('event', 'student.profile_updated')->latest('created_at')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('INEE-PRIVATE-001', $audit->old_values ?? '');
        $this->assertStringNotContainsString('+224620000000', $audit->new_values ?? '');
        $this->assertStringNotContainsString('NID-SECRET-1', $audit->new_values ?? '');
        $this->assertStringContainsString('[REDACTED]', $audit->new_values ?? '');
    }

    public function test_public_news_escapes_untrusted_html_content(): void
    {
        News::create([
            'title' => 'Article de test XSS', 'slug' => 'article-test-xss',
            'body' => '<script>alert(document.cookie)</script>', 'status' => 'published',
            'visibility' => 'public', 'published_at' => now(),
        ]);

        $this->get('/news/article-test-xss')
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(document.cookie)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(document.cookie)</script>', false);
    }

    public function test_mutating_web_routes_remain_inside_the_csrf_protected_web_group(): void
    {
        $route = Route::getRoutes()->getByName('admin.applications.workflow.commit');

        $this->assertNotNull($route);
        $this->assertContains('web', $route->middleware());
        $this->assertContains('auth', $route->middleware());
        $this->assertContains('permission:applications.finance', $route->middleware());
    }
}