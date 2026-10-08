<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardStatisticsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DecisionStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_summary_uses_canonical_statuses_and_real_general_kpis(): void
    {
        $student = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => 'STAT-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        $program = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $program, 'name' => 'Programme statistiques', 'code' => 'STAT-'.Str::random(6), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $call = (string) Str::uuid();
        DB::table('calls')->insert(['id' => $call, 'program_id' => $program, 'title' => 'Appel statistiques', 'reference' => 'CALL-'.Str::random(6), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $application = (string) Str::uuid();
        DB::table('applications')->insert(['id' => $application, 'call_id' => $call, 'program_id' => $program, 'applicant_id' => $student->id, 'reference' => 'APP-'.Str::random(6), 'status' => 'soumis', 'workflow_status' => 'awarded', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('application_awards')->insert(['id' => (string) Str::uuid(), 'application_id' => $application, 'beneficiary_id' => $student->id, 'program_id' => $program, 'amount' => 1000, 'award_date' => today(), 'decision_reference' => 'DEC-STAT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $summary = app(DashboardStatisticsService::class)->summary(['test' => Str::random(8)]);

        $this->assertSame(1, $summary['students']);
        $this->assertSame(1, $summary['applications']);
        $this->assertSame(1, $summary['validated']);
        $this->assertSame(1, $summary['beneficiaries']);
        $this->assertSame(1, $summary['active_programs']);
    }

    public function test_statistics_api_requires_permission(): void
    {
        $user = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);
        $user->assignRole('etudiant');
        $this->actingAs($user)->getJson('/api/v1/statistics/overview')->assertForbidden();
    }
}
