<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentPortalExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_can_browse_programs_and_open_eligibility_assistant(): void
    {
        $student = $this->student();
        $programId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Aide de rentrée', 'code' => 'RENTREE-'.Str::random(5), 'type' => 'education', 'description' => 'Soutien étudiant', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($student)->get('/student/programs')->assertOk()->assertSee('Aide de rentrée');
        $this->actingAs($student)->get('/student/programs/'.$programId.'/eligibility')->assertOk()->assertSee('ASSISTANT D’ÉLIGIBILITÉ');
    }

    public function test_student_results_are_scoped_to_the_authenticated_student(): void
    {
        $owner = $this->student();
        $other = $this->student();
        [$programId, $callId] = $this->programAndCall();
        $ownedApplication = $this->application($owner, $programId, $callId, 'OWN-'.Str::random(6));
        $otherApplication = $this->application($other, $programId, $callId, 'OTH-'.Str::random(6));
        foreach ([$ownedApplication => 'OWN', $otherApplication => 'OTH'] as $applicationId => $label) {
            DB::table('application_results')->insert(['id' => (string) Str::uuid(), 'application_id' => $applicationId, 'decision' => 'accepted', 'reason' => $label, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($owner)->get('/student/results')->assertOk()->assertSee('OWN')->assertDontSee('OTH');
    }

    public function test_student_notifications_are_scoped_and_profile_changes_are_audited(): void
    {
        $student = $this->student();
        $other = $this->student();
        DB::table('notifications')->insert([
            ['id' => (string) Str::uuid(), 'type' => 'student.test', 'notifiable_type' => User::class, 'notifiable_id' => $student->id, 'data' => json_encode(['message' => 'Notification privée']), 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'type' => 'student.test', 'notifiable_type' => User::class, 'notifiable_id' => $other->id, 'data' => json_encode(['message' => 'Notification étrangère']), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($student)->get('/student/notifications')->assertOk()->assertSee('Notification privée')->assertDontSee('Notification étrangère');
        $this->actingAs($student)->put('/student/profile', ['phone' => '07000000', 'program' => 'Droit'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['event' => 'student.profile_updated', 'user_id' => $student->id]);
    }

    public function test_dashboard_shows_real_owned_funding_documents_and_notifications_only(): void
    {
        $student = $this->student();
        [$programId, $callId] = $this->programAndCall();
        $applicationId = $this->application($student, $programId, $callId, 'DASH-'.Str::random(6));
        DB::table('application_awards')->insert([
            'id' => (string) Str::uuid(), 'application_id' => $applicationId, 'beneficiary_id' => $student->id,
            'program_id' => $programId, 'amount' => 450000, 'award_date' => today(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $commitmentId = (string) Str::uuid();
        $disbursementId = (string) Str::uuid();
        DB::table('financial_commitments')->insert([
            'id' => $commitmentId, 'application_id' => $applicationId, 'reference' => 'ENG-'.Str::random(6),
            'amount' => 450000, 'currency' => 'FCFA', 'status' => 'approved', 'committed_at' => today(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('disbursements')->insert([
            'id' => $disbursementId, 'commitment_id' => $commitmentId, 'reference' => 'DIS-'.Str::random(6),
            'amount' => 300000, 'status' => 'execute', 'scheduled_for' => today(), 'disbursed_at' => today(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payment_records')->insert([
            'id' => (string) Str::uuid(), 'disbursement_id' => $disbursementId, 'provider_reference' => 'PAY-DASH-OWN',
            'amount' => 300000, 'status' => 'paid', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $documentId = (string) Str::uuid();
        DB::table('documents')->insert([
            'id' => $documentId, 'uploaded_by' => $student->id, 'title' => 'Certificat du tableau',
            'document_type' => 'certificat', 'disk' => 'private', 'path' => 'students/certificat.pdf',
            'visibility' => 'private', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('application_documents')->insert([
            'id' => (string) Str::uuid(), 'application_id' => $applicationId, 'document_id' => $documentId,
            'status' => 'validated', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'dashboard.test', 'notifiable_type' => User::class,
            'notifiable_id' => $student->id, 'data' => json_encode(['subject' => 'Avis personnel tableau']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('events')->insert([
            'id' => (string) Str::uuid(), 'title' => 'Rencontre étudiante FOSER', 'slug' => 'rencontre-etudiante-'.Str::random(6),
            'venue' => 'Ouagadougou', 'starts_at' => now()->addDays(3), 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $otherStudent = $this->student();
        [$otherProgramId, $otherCallId] = $this->programAndCall();
        $otherApplicationId = $this->application($otherStudent, $otherProgramId, $otherCallId, 'DASH-'.Str::random(6));
        DB::table('application_awards')->insert([
            'id' => (string) Str::uuid(), 'application_id' => $otherApplicationId, 'beneficiary_id' => $otherStudent->id,
            'program_id' => $otherProgramId, 'amount' => 999000, 'award_date' => today(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'dashboard.test', 'notifiable_type' => User::class,
            'notifiable_id' => $otherStudent->id, 'data' => json_encode(['subject' => 'Avis privé étranger']),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student)->get('/student')->assertOk()
            ->assertSee('450 000')->assertSee('300 000')->assertSee('150 000')
            ->assertSee('Certificat du tableau')->assertSee('Avis personnel tableau')->assertSee('Rencontre étudiante FOSER')
            ->assertDontSee('999 000')->assertDontSee('Avis privé étranger');
    }

    private function student(): User
    {
        $student = User::factory()->create(['account_type' => 'etudiant', 'email_verified_at' => now()]);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => 'INEE-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        return $student;
    }

    private function programAndCall(): array
    {
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme test', 'code' => 'TEST-'.Str::random(6), 'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel test', 'reference' => 'CALL-'.Str::random(6), 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        return [$programId, $callId];
    }

    private function application(User $student, string $programId, string $callId, string $reference): string
    {
        $id = (string) Str::uuid();
        DB::table('applications')->insert(['id' => $id, 'call_id' => $callId, 'program_id' => $programId, 'applicant_id' => $student->id, 'reference' => $reference, 'status' => 'approuve', 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }
}
