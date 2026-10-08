<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentPortalActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_profile_updates_personal_information_but_cannot_change_university_affiliation(): void
    {
        $student = $this->student();
        $universityId = $this->university('UniversitÃ© initiale');
        $nextUniversityId = $this->university('UniversitÃ© suivante');
        DB::table('student_profiles')->where('user_id', $student->id)->update(['university_id' => $universityId]);

        $this->actingAs($student)->put('/student/profile', [
            'phone' => '70000000', 'address' => 'Adresse Ã©tudiante', 'program' => 'Informatique',
            'academic_year' => '2026-2027', 'faculty' => 'Sciences', 'study_level' => 'Licence 2',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id, 'university_id' => $universityId,
            'program' => 'Informatique', 'academic_year' => '2026-2027', 'study_level' => 'Licence 2',
        ]);

        $this->actingAs($student)->put('/student/profile', [
            'phone' => '71111111', 'university_id' => $nextUniversityId,
        ])->assertSessionHasErrors('university_id');
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'university_id' => $universityId]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'student.profile_updated', 'user_id' => $student->id]);
    }

    public function test_student_profile_keeps_payment_phones_cnib_and_nip_as_strings(): void
    {
        $student = $this->student();
        $this->actingAs($student)->get('/student/profile')
            ->assertOk()->assertSee('Numéro de téléphone pour le paiement')
            ->assertSee('Autre numéro de téléphone')->assertSee('name="national_id"', false)
            ->assertSee('name="nip"', false)->assertSee('maxlength="20"', false);

        $cnib = 'CNIB-0000123456789';
        $nip = '00001234567890';
        $this->put('/student/profile', [
            'phone' => '07000001', 'other_phone' => '0022607000002',
            'national_id' => $cnib, 'nip' => $nip,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id, 'phone' => '07000001', 'other_phone' => '0022607000002',
            'national_id' => $cnib, 'nip' => $nip,
        ]);

        $this->put('/student/profile', [
            'phone' => '07000001', 'national_id' => str_repeat('A', 21), 'nip' => str_repeat('0', 21),
        ])->assertSessionHasErrors(['national_id', 'nip']);
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'national_id' => $cnib, 'nip' => $nip]);
    }

    public function test_student_profile_saves_both_parents_information(): void
    {
        $student = $this->student();
        $this->actingAs($student)->get('/student/profile')
            ->assertOk()->assertSee('Informations du père')->assertSee('Informations de la mère')
            ->assertSee('name="father_first_name"', false)->assertSee('name="mother_first_name"', false);

        $parentInformation = [
            'phone' => '07000000',
            'father_first_name' => 'Jean', 'father_last_name' => 'KABORE',
            'father_residence_country' => 'Burkina Faso', 'father_function' => 'Enseignant',
            'mother_first_name' => 'Aminata', 'mother_last_name' => 'TRAORE',
            'mother_residence_country' => 'Côte d’Ivoire', 'mother_function' => 'Commerçante',
        ];
        $this->put('/student/profile', $parentInformation)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, ...$parentInformation]);

        $this->put('/student/profile', [...$parentInformation, 'father_first_name' => str_repeat('A', 121)])
            ->assertSessionHasErrors('father_first_name');
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id, 'father_first_name' => 'Jean']);
    }

    public function test_student_can_only_view_and_print_attestation_for_own_active_award(): void
    {
        $owner = $this->student();
        $other = $this->student();
        $ownedApplication = $this->application($owner, 'approuve');
        $otherApplication = $this->application($other, 'approuve');
        $this->award($owner, $ownedApplication, 125000);
        $this->award($other, $otherApplication, 250000);

        $this->actingAs($owner)->get('/student/attestations/'.$ownedApplication)
            ->assertOk()->assertSee('Attestation d’attribution')->assertSee('125 000')->assertSee($owner->name);
        $this->actingAs($owner)->get('/student/attestations/'.$otherApplication)->assertNotFound();
    }

    public function test_student_payment_history_contains_only_paid_records_from_owned_applications(): void
    {
        $owner = $this->student();
        $other = $this->student();
        $ownedApplication = $this->application($owner, 'paye');
        $otherApplication = $this->application($other, 'paye');
        $paidDisbursement = $this->disbursement($ownedApplication, $owner, 'paid');
        $pendingDisbursement = $this->disbursement($ownedApplication, $owner, 'pending');
        $foreignDisbursement = $this->disbursement($otherApplication, $other, 'paid');
        $this->payment($paidDisbursement, 'PAY-OWN-PAID', 'paid');
        $this->payment($pendingDisbursement, 'PAY-OWN-PENDING', 'pending');
        $this->payment($foreignDisbursement, 'PAY-OTHER-PAID', 'paid');

        $this->actingAs($owner)->get('/student/payments')
            ->assertOk()->assertSee('PAY-OWN-PAID')->assertDontSee('PAY-OWN-PENDING')->assertDontSee('PAY-OTHER-PAID');
    }

    public function test_student_can_create_private_claim_and_message_thread(): void
    {
        $student = $this->student();
        $other = $this->student();
        $staff = User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
        $staff->assignRole('admin');
        $this->actingAs($student)->post('/student/claims', [
            'subject' => 'Question sur mon dossier', 'description' => 'Message confidentiel du candidat',
        ])->assertRedirect();
        $this->post('/student/messages', ['subject' => 'Question', 'body' => 'Message privé à mon agent'])->assertRedirect();

        $this->assertDatabaseHas('claims', ['claimant_id' => $student->id, 'subject' => 'Question sur mon dossier']);
        $threadId = (string) DB::table('message_threads')->where('subject', 'Question')->value('id');
        $this->assertDatabaseHas('message_thread_users', ['thread_id' => $threadId, 'user_id' => $student->id]);
        $this->assertDatabaseHas('message_thread_users', ['thread_id' => $threadId, 'user_id' => $staff->id]);
        $this->get('/student/support')->assertOk()->assertSee('Question')->assertSee('Message privé à mon agent')->assertSee('R&eacute;pondre', false);
        $this->post('/student/messages/'.$threadId.'/reply', ['body' => 'Complément de ma demande'])->assertRedirect();
        $this->assertDatabaseHas('messages', ['thread_id' => $threadId, 'sender_id' => $student->id, 'body' => 'Complément de ma demande']);

        $this->actingAs($other)->get('/student/support')->assertOk()
            ->assertDontSee('Question sur mon dossier')->assertDontSee('Message privé à mon agent')->assertDontSee('Complément de ma demande');
        $this->post('/student/messages/'.$threadId.'/reply', ['body' => 'Accès interdit'])->assertNotFound();
    }

    public function test_application_timeline_displays_all_required_workflow_stages(): void
    {
        $student = $this->student();
        $this->application($student, 'evaluation');

        $response = $this->actingAs($student)->get('/student/applications')->assertOk();
        foreach (['BROUILLON', 'PIÈCES', 'CONTRÔLE', 'SOUMIS', 'VÉRIFICATION UNIVERSITÉ', 'VÉRIFICATION FOSER', 'ÉVALUATION', 'DÉCISION', 'ATTRIBUTION', 'DÉCAISSEMENT', 'TERMINÉ'] as $stage) {
            $response->assertSee($stage);
        }
    }

    private function student(): User
    {
        $student = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $student->id,
            'inee' => 'INEE-'.Str::random(8), 'ine_status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $student;
    }

    private function university(string $name): string
    {
        $id = (string) Str::uuid();
        DB::table('universities')->insert([
            'id' => $id, 'name' => $name, 'code' => 'UNIV-'.Str::random(6),
            'country' => 'Burkina Faso', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function application(User $student, string $status): string
    {
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        $applicationId = (string) Str::uuid();
        $reference = 'STU-'.Str::random(8);
        DB::table('programs')->insert([
            'id' => $programId, 'name' => 'Bourse Ã©tudiant', 'code' => $reference,
            'type' => 'education', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('calls')->insert([
            'id' => $callId, 'program_id' => $programId, 'title' => 'Appel Ã©tudiant',
            'reference' => $reference, 'opens_at' => today()->subDay(), 'closes_at' => today()->addDay(),
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('applications')->insert([
            'id' => $applicationId, 'call_id' => $callId, 'program_id' => $programId,
            'applicant_id' => $student->id, 'reference' => $reference, 'status' => $status,
            'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $applicationId;
    }

    private function award(User $student, string $applicationId, int $amount): void
    {
        DB::table('application_awards')->insert([
            'id' => (string) Str::uuid(), 'application_id' => $applicationId,
            'beneficiary_id' => $student->id, 'program_id' => DB::table('applications')->where('id', $applicationId)->value('program_id'),
            'amount' => $amount, 'award_date' => today(), 'decision_reference' => 'DEC-'.Str::random(6),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function disbursement(string $applicationId, User $student, string $suffix): string
    {
        $commitmentId = (string) Str::uuid();
        $disbursementId = (string) Str::uuid();
        DB::table('financial_commitments')->insert([
            'id' => $commitmentId, 'application_id' => $applicationId, 'beneficiary_id' => $student->id,
            'reference' => 'ENG-'.Str::random(8), 'amount' => 1000, 'currency' => 'FCFA',
            'status' => 'execute', 'committed_at' => today(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('disbursements')->insert([
            'id' => $disbursementId, 'commitment_id' => $commitmentId, 'reference' => 'DIS-'.Str::random(8),
            'amount' => 1000, 'status' => 'execute', 'scheduled_for' => today(), 'disbursed_at' => today(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $disbursementId;
    }

    private function payment(string $disbursementId, string $reference, string $status): void
    {
        DB::table('payment_records')->insert([
            'id' => (string) Str::uuid(), 'disbursement_id' => $disbursementId,
            'provider_reference' => $reference, 'payment_method' => 'bank_transfer', 'amount' => 1000,
            'status' => $status, 'paid_at' => $status === 'paid' ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

