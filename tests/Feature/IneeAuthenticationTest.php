<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class IneeAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_registration_stores_inee_as_string_and_sends_it_by_email(): void
    {
        Mail::fake();
        $this->post('/student/register', [
            'name' => 'Alice INEE', 'email' => 'alice.inee@example.com', 'phone' => '70000000',
            'inee' => '001245789', 'terms' => '1', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect();

        $this->assertDatabaseHas('student_profiles', ['inee' => '001245789']);
        $this->assertIsString(DB::table('student_profiles')->where('inee', '001245789')->value('inee'));
        Mail::assertSentCount(1);
    }

    public function test_student_can_login_with_email_or_inee_and_invalid_credentials_are_generic(): void
    {
        $student = User::factory()->create(['email' => 'inee@example.com', 'account_type' => 'etudiant', 'status' => 'active', 'email_verified_at' => now(), 'password' => bcrypt('password123')]);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => '001245789', 'created_at' => now(), 'updated_at' => now()]);

        $this->post('/student/login', ['login' => 'inee@example.com', 'password' => 'password123'])->assertRedirect();
        Auth::logout();
        $this->post('/student/login', ['login' => '001245789', 'password' => 'password123'])->assertRedirect();
        Auth::logout();
        $this->post('/student/login', ['login' => 'wrong-code', 'password' => 'password123'])->assertSessionHasErrors('login');
    }

    public function test_inee_is_unique(): void
    {
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id, 'inee' => '001245789', 'created_at' => now(), 'updated_at' => now()]);
        $this->from('/student/register')->post('/student/register', ['name' => 'Second', 'email' => 'second@example.com', 'phone' => '70000001', 'inee' => '001245789', 'terms' => '1', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertSessionHasErrors('inee');
    }

    public function test_student_api_returns_inee_as_string(): void
    {
        $student = User::factory()->create(['account_type' => 'etudiant']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => '001245789', 'created_at' => now(), 'updated_at' => now()]);
        $apiUser = User::factory()->create();
        $apiUser->givePermissionTo('users.view');
        Sanctum::actingAs($apiUser, ['*']);

        $this->getJson('/api/v1/students/'.$student->id)->assertOk()->assertJsonPath('data.inee', '001245789');
    }
}
