<?php

namespace Tests\Feature;

use App\Models\User;
use App\Jobs\SendIneRecoveryCode;
use App\Jobs\SendIneLoginCode;
use App\Mail\IneRecoveryCodeMail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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

    public function test_ine_service_pages_replace_old_direct_forms(): void
    {
        $this->get(route('ine.index'))->assertOk()->assertSee('Gestion de mon INE')->assertSee('Je connais mon INE')->assertSee('Je ne connais pas mon INE');
        $this->get(route('ine.declare'))->assertOk()->assertSee('name="last_name"', false)->assertSee('name="first_name"', false)->assertSee('name="inee"', false);
        $this->get(route('ine.recover'))->assertOk()->assertSee('Rechercher mon INE')->assertSee('birth_place');
        $this->get('/recherche-ine')->assertRedirect('/ine/rechercher');
    }

    public function test_registration_requires_an_ine_declaration_and_never_accepts_a_raw_ine(): void
    {
        Mail::fake();
        $payload = [
            'name' => 'Alice INEE', 'email' => 'alice.inee@example.com', 'phone' => '70000000',
            'birth_place' => 'Ouagadougou',
            'terms' => '1', 'password' => 'password123', 'password_confirmation' => 'password123',
        ];
        $this->post('/student/register', [...$payload, 'birth_place' => ''])->assertSessionHasErrors('birth_place');
        $this->post('/student/register', [...$payload, 'inee' => 'RAW-INEE-1'])->assertSessionHasErrors('inee');
        $this->assertDatabaseMissing('users', ['email' => 'alice.inee@example.com']);

        $this->post(route('ine.verify'), ['last_name' => 'INEE', 'first_name' => 'Alice', 'inee' => 'DECLARED-INEE-1'])->assertRedirect();
        $universityId = (string) Str::uuid();
        DB::table('universities')->insert(['id' => $universityId, 'name' => 'Université revendiquée', 'code' => 'CLAIM-'.Str::random(5), 'country' => 'Burkina Faso', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/student/register', [...$payload, 'university_id' => $universityId])->assertSessionHasErrors('university_id');
        $this->post('/student/register', $payload)->assertRedirect(route('ine.declare'));

        $this->assertDatabaseHas('student_profiles', ['user_id' => User::where('email', 'alice.inee@example.com')->value('id'), 'inee' => 'DECLARED-INEE-1', 'birth_place' => 'Ouagadougou', 'ine_status' => 'pending', 'university_id' => null]);
        Mail::assertNotQueued(IneRecoveryCodeMail::class);
    }

    public function test_student_can_login_with_email_or_inee_and_invalid_credentials_are_generic(): void
    {
        $loginCode = '0000123456';
        $student = User::factory()->create(['email' => 'inee@example.com', 'account_type' => 'etudiant', 'status' => 'active', 'email_verified_at' => now(), 'password' => bcrypt('password123')]);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => '001245789', 'ine_status' => 'pending', 'ine_login_code_hash' => Hash::make($loginCode), 'created_at' => now(), 'updated_at' => now()]);

        $this->post('/student/login', ['login' => 'inee@example.com', 'password' => 'password123'])->assertRedirect();
        Auth::logout();
        $this->post('/student/login', ['login' => '001245789', 'password' => $loginCode])->assertRedirect(route('student.dashboard'));
        Auth::logout();
        $this->post('/student/login', ['login' => '001245789', 'password' => 'password123'])->assertSessionHasErrors('login');
        $this->post('/student/login', ['login' => 'wrong-code', 'password' => '1234567890'])->assertSessionHasErrors('login');
    }

    public function test_logged_in_student_can_declare_ine_for_own_profile(): void
    {
        Queue::fake();
        $student = $this->student('Alice INEE');
        $this->actingAs($student)->post(route('ine.verify'), ['last_name' => 'INEE', 'first_name' => 'Alice', 'inee' => 'n04990420252'])->assertRedirect(route('ine.declare'));
        $this->get(route('ine.declare'))->assertOk()->assertSee('N04990420252')->assertSee('Vérification préalable effectuée');
        $this->post(route('ine.confirm'))->assertRedirect(route('ine.declare'));
        $profile = DB::table('student_profiles')->where('user_id', $student->id)->first();
        $this->assertSame('N04990420252', $profile->inee);
        $this->assertSame('pending', $profile->ine_status);
        $job = Queue::pushed(SendIneLoginCode::class)->first();
        $code = Crypt::decryptString($job->encryptedCode);
        $this->assertMatchesRegularExpression('/\A[0-9]{10}\z/', $code);
        $this->assertTrue(Hash::check($code, $profile->ine_login_code_hash));
        $this->assertDatabaseHas('audit_logs', ['event' => 'ine.declared', 'user_id' => $student->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ine.login_code_issued', 'user_id' => $student->id]);
    }

    public function test_declaration_rejects_invalid_and_already_associated_ines(): void
    {
        $student = $this->student('Alice INEE');
        $this->actingAs($student)->post(route('ine.verify'), ['last_name' => 'INEE', 'first_name' => 'Alice', 'inee' => 'bad code'])->assertSessionHasErrors('inee');
        $this->student('Other Student', 'OTHER-INE-1');
        $this->actingAs($student)->post(route('ine.verify'), ['last_name' => 'INEE', 'first_name' => 'Alice', 'inee' => 'OTHER-INE-1'])->assertSessionHasErrors('inee');
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'inee' => null]);
    }

    public function test_guest_can_prepare_declaration_but_must_authenticate_before_association(): void
    {
        Queue::fake();
        $student = $this->student('Alice INEE');
        $this->post(route('ine.verify'), ['last_name' => 'INEE', 'first_name' => 'Alice', 'inee' => 'N04990420252'])->assertRedirect(route('ine.declare'));
        $this->get(route('ine.declare'))->assertOk()->assertSee('Se connecter')->assertDontSee('Confirmer mon INE');
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'inee' => null]);

        $this->post('/student/login', ['login' => $student->email, 'password' => 'password123'])->assertRedirect(route('ine.declare'));
        $this->post(route('ine.confirm'))->assertRedirect(route('ine.declare'));
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'inee' => 'N04990420252', 'ine_status' => 'pending']);
        Queue::assertPushed(SendIneLoginCode::class);
    }

    public function test_recovery_response_is_generic_and_does_not_disclose_account_data(): void
    {
        Queue::fake();
        $this->post(route('ine.search'), ['last_name' => 'Unknown', 'first_name' => 'Student', 'date_of_birth' => '2000-01-01', 'birth_place' => 'Ouagadougou'])
            ->assertRedirect(route('ine.recover'));
        $this->get(route('ine.recover'))->assertOk()->assertSee('Si une correspondance existe')->assertDontSee('Unknown');
        $this->post(route('ine.otp.send'))->assertRedirect(route('ine.recover'));
        Queue::assertNotPushed(SendIneRecoveryCode::class);
    }

    public function test_unverified_ine_is_not_recovered_or_sent_by_otp(): void
    {
        Queue::fake();
        $this->student('Alice INEE', 'PENDING-INE-1', '2000-01-01', 'Ouagadougou');
        $this->post(route('ine.search'), ['last_name' => 'INEE', 'first_name' => 'Alice', 'date_of_birth' => '2000-01-01', 'birth_place' => 'Ouagadougou']);
        $this->post(route('ine.otp.send'));
        Queue::assertNotPushed(SendIneRecoveryCode::class);
        $this->get(route('ine.recover'))->assertDontSee('PENDING-INE-1');
    }

    public function test_recovery_sends_otp_and_reveals_ine_only_after_valid_code(): void
    {
        Queue::fake();
        $student = $this->student('Alice INEE', '001245789', '2000-01-01', 'Ouagadougou', 'verified');
        $this->post(route('ine.search'), ['last_name' => 'INEE', 'first_name' => 'Alice', 'date_of_birth' => '2000-01-01', 'birth_place' => 'Ouagadougou']);
        $this->get(route('ine.recover'))->assertOk()->assertSee('Si une correspondance existe')->assertDontSee('001245789')->assertDontSee($student->email);
        $this->post(route('ine.otp.send'));
        Queue::assertPushed(SendIneRecoveryCode::class);
        $job = Queue::pushed(SendIneRecoveryCode::class)->first();
        $code = Crypt::decryptString($job->encryptedCode);
        $this->assertNotSame($code, $job->encryptedCode);
        $this->post(route('ine.otp.verify'), ['code' => $code])->assertRedirect(route('ine.recover'));
        $this->get(route('ine.recover'))->assertOk()->assertSee('001245789')->assertSee('Identité vérifiée')->assertDontSee($student->email);
        $this->post(route('ine.otp.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertDatabaseHas('audit_logs', ['event' => 'ine.recovery.verified', 'user_id' => null]);
    }

    public function test_invalid_recovery_otp_is_rate_limited_by_attempt_count(): void
    {
        Queue::fake();
        $this->student('Alice INEE', '001245789', '2000-01-01', 'Ouagadougou', 'verified');
        $this->post(route('ine.search'), ['last_name' => 'INEE', 'first_name' => 'Alice', 'date_of_birth' => '2000-01-01', 'birth_place' => 'Ouagadougou']);
        $this->post(route('ine.otp.send'));
        $job = Queue::pushed(SendIneRecoveryCode::class)->first();
        $actualCode = Crypt::decryptString($job->encryptedCode);
        $wrongCode = $actualCode === '000000' ? '000001' : '000000';
        foreach (range(1, 5) as $attempt) {
            $response = $this->post(route('ine.otp.verify'), ['code' => $wrongCode]);
            $this->assertSame(302, $response->status(), 'Unexpected response status on OTP attempt '.$attempt.'.');
            $response->assertSessionHasErrors('code');
        }
        $this->post(route('ine.otp.verify'), ['code' => $wrongCode])->assertSessionHasErrors('code');
        $this->get(route('ine.recover'))->assertDontSee('001245789');
    }

    public function test_expired_recovery_otp_cannot_reveal_the_ine(): void
    {
        Queue::fake();
        $this->student('Alice INEE', '001245789', '2000-01-01', 'Ouagadougou', 'verified');
        $this->post(route('ine.search'), ['last_name' => 'INEE', 'first_name' => 'Alice', 'date_of_birth' => '2000-01-01', 'birth_place' => 'Ouagadougou']);
        $this->post(route('ine.otp.send'));
        $job = Queue::pushed(SendIneRecoveryCode::class)->first();
        $this->travel(11)->minutes();

        $this->post(route('ine.otp.verify'), ['code' => Crypt::decryptString($job->encryptedCode)])->assertSessionHasErrors('code');
        $this->get(route('ine.recover'))->assertDontSee('001245789');
    }

    public function test_search_endpoint_is_rate_limited(): void
    {
        $payload = ['last_name' => 'Unknown', 'first_name' => 'Student', 'date_of_birth' => '2000-01-01', 'birth_place' => 'Ouagadougou'];
        foreach (range(1, 5) as $attempt) {
            $this->post(route('ine.search'), $payload)->assertRedirect();
        }
        $this->post(route('ine.search'), $payload)->assertTooManyRequests();
    }

    public function test_ine_login_code_is_unavailable_for_suspended_or_rejected_statuses(): void
    {
        $loginCode = '0987654321';
        foreach (['suspended', 'rejected'] as $status) {
            $student = $this->student('Student '.$status, 'BLOCKED-'.strtoupper($status));
            DB::table('student_profiles')->where('user_id', $student->id)->update(['ine_status' => $status, 'ine_login_code_hash' => Hash::make($loginCode)]);
            $this->post('/student/login', ['login' => DB::table('student_profiles')->where('user_id', $student->id)->value('inee'), 'password' => $loginCode])->assertSessionHasErrors('login');
        }
    }

    public function test_admin_can_verify_reject_suspend_and_reactivate_an_ine(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $student = $this->student('Alice INEE', 'ADMIN-INE-1');
        $service = app(\App\Services\IneAdministrationService::class);

        $service->changeStatus($admin, $student, 'verified');
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'ine_status' => 'verified', 'ine_verified_by' => $admin->id]);
        $this->assertNotNull(DB::table('student_profiles')->where('user_id', $student->id)->value('ine_verified_at'));
        $service->changeStatus($admin, $student, 'suspended');
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'ine_status' => 'suspended']);
        $service->changeStatus($admin, $student, 'verified');
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'ine_status' => 'verified']);
        $rejected = $this->student('Rejected Student', 'ADMIN-INE-2');
        $service->changeStatus($admin, $rejected, 'rejected');
        $this->assertDatabaseHas('student_profiles', ['user_id' => $rejected->id, 'ine_status' => 'rejected']);
        $this->assertDatabaseCount('audit_logs', 4);
    }

    public function test_admin_cannot_apply_an_invalid_ine_status_transition(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $admin->assignRole('admin');
        $student = $this->student('Alice INEE', 'ADMIN-INE-2');

        try {
            app(\App\Services\IneAdministrationService::class)->changeStatus($admin, $student, 'suspended');
            $this->fail('A pending INE cannot be suspended without prior verification.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('ine_status', $exception->errors());
        }

        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'ine_status' => 'pending']);
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

    private function student(string $name, ?string $inee = null, ?string $birthDate = null, ?string $birthPlace = null, string $ineStatus = 'pending'): User
    {
        $student = User::factory()->create([
            'name' => $name,
            'email' => Str::slug($name).'-'.Str::random(5).'@example.test',
            'account_type' => 'etudiant',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => $inee,
            'ine_status' => $ineStatus, 'date_of_birth' => $birthDate, 'birth_place' => $birthPlace,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $student;
    }
}
