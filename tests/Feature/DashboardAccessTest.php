<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardStatisticsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_can_access_only_the_student_dashboard(): void
    {
        $student = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);

        $this->actingAs($student)->get('/student')->assertOk();
        $this->actingAs($student)->get('/researcher')->assertForbidden();
        $this->actingAs($student)->get('/evaluator/dashboard')->assertForbidden();
    }

    public function test_each_staff_dashboard_requires_its_role(): void
    {
        $roles = [
            ['agent_dossier', '/agent/dashboard'],
            ['agent_finance', '/finance/dashboard'],
            ['agent_recherche', '/research/dashboard'],
            ['agent_communication', '/communication/dashboard'],
            ['gestionnaire', '/manager/dashboard'],
            ['directeur_general', '/director/dashboard'],
            ['evaluateur', '/evaluator/dashboard'],
            ['partenaire', '/partner/dashboard'],
        ];

        foreach ($roles as [$role, $path]) {
            $user = User::factory()->create(['account_type' => $role, 'status' => 'active']);
            $this->actingAs($user)->get($path)->assertOk();
            $this->actingAs($user)->get('/student')->assertForbidden();
        }
    }

    public function test_inactive_user_cannot_access_a_dashboard(): void
    {
        $user = User::factory()->create(['account_type' => 'etudiant', 'status' => 'disabled']);

        $this->actingAs($user)->get('/student')->assertForbidden();
    }

    public function test_dashboard_statistics_support_date_grouping(): void
    {
        $charts = app(DashboardStatisticsService::class)->charts(['test' => uniqid()]);

        $this->assertArrayHasKey('monthly', $charts);
        $this->assertArrayHasKey('annual', $charts);
        $this->assertIsArray($charts['monthly']);
        $this->assertIsArray($charts['annual']);
    }

    public function test_researcher_cannot_access_another_researchers_project(): void
    {
        $owner = User::factory()->create(['account_type' => 'chercheur', 'status' => 'active']);
        $other = User::factory()->create(['account_type' => 'chercheur', 'status' => 'active']);
        $projectId = (string) Str::uuid();
        DB::table('research_projects')->insert([
            'id' => $projectId,
            'principal_researcher_id' => $owner->id,
            'title' => 'Projet privé',
            'reference' => 'PRJ-'.uniqid(),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($other)->get(route('researcher.projects.show', $projectId))->assertNotFound();
        $this->actingAs($other)->post(route('researcher.projects.submit', $projectId))->assertNotFound();
    }

    public function test_researcher_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create(['account_type' => 'chercheur', 'status' => 'active']);
        $other = User::factory()->create(['account_type' => 'chercheur', 'status' => 'active']);
        $notificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($other)->postJson(route('notifications.read', $notificationId))->assertNotFound();
        $this->assertDatabaseHas('notifications', ['id' => $notificationId, 'read_at' => null]);
    }
}
