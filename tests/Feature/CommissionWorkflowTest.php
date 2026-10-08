<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\Commissions\CommissionResource;
use App\Models\Commission;
use App\Models\User;
use App\Services\CommissionWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    public function test_commission_management_is_permission_gated_and_membership_is_explicit(): void
    {
        [$manager, $evaluators, $commission, $applicationId] = $this->commissionContext();
        $outsider = $this->evaluator('outsider');
        DB::table('evaluations')->insert($this->evaluationRow($applicationId, $outsider->id, 'validated'));

        $this->actingAs($outsider)->get(route('commissions.show', $commission))->assertForbidden();
        $this->actingAs($evaluators[0])->get(route('commissions.show', $commission))->assertOk()->assertSee('Dossiers inscrits')->assertSee('Pièces manquantes')->assertSee('Attestation de recherche');

        $this->actingAs($outsider);
        $this->assertFalse(CommissionResource::canAccess());
        $this->actingAs($manager);
        $this->assertTrue(CommissionResource::canAccess());
        $this->assertDatabaseHas('commission_applications', ['commission_id' => $commission->id, 'application_id' => $applicationId]);
    }

    public function test_filament_commission_resource_is_not_available_to_an_evaluator_without_manage_permission(): void
    {
        $evaluator = $this->evaluator('resource-access');
        $admin = $this->admin('resource-admin@example.test');

        $this->actingAs($evaluator)->get('/admin/commissions')->assertForbidden();
        $this->actingAs($admin)->get('/admin/commissions')->assertOk()->assertSee('Commissions');

        DB::table('users')->where('id', $admin->id)->update(['status' => 'suspended']);
        $this->actingAs($admin->fresh())->get('/admin/commissions')->assertForbidden();
    }

    public function test_quorum_blocks_meeting_and_only_present_member_can_cast_one_vote(): void
    {
        [$manager, $evaluators, $commission, $applicationId] = $this->commissionContext();
        $workflow = app(CommissionWorkflowService::class);
        $item = $commission->applications()->where('application_id', $applicationId)->firstOrFail();

        $workflow->schedule($manager, $commission);
        $workflow->sendConvocations($manager, $commission);
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $manager->id)->value('id'), 'present');
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $evaluators[0]->id)->value('id'), 'absent');
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $evaluators[1]->id)->value('id'), 'absent');

        $this->assertFalse($workflow->quorum($commission->fresh())['reached']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $workflow->startMeeting($manager, $commission->fresh());
    }

    public function test_absent_or_non_member_cannot_vote_and_duplicate_votes_are_rejected(): void
    {
        [$manager, $evaluators, $commission, $applicationId] = $this->commissionContext();
        $workflow = app(CommissionWorkflowService::class);
        $item = $commission->applications()->where('application_id', $applicationId)->firstOrFail();
        $workflow->schedule($manager, $commission);
        $workflow->sendConvocations($manager, $commission);
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $manager->id)->value('id'), 'present');
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $evaluators[0]->id)->value('id'), 'present');
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $evaluators[1]->id)->value('id'), 'absent');
        $workflow->startMeeting($manager, $commission);

        try {
            $workflow->castVote($evaluators[1], $commission, $item->id, 'favorable');
            $this->fail('Un membre absent ne doit pas voter.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertDatabaseCount('commission_votes', 0);
        }

        $workflow->castVote($evaluators[0], $commission, $item->id, 'favorable', 'Avis favorable.');
        try {
            $workflow->castVote($evaluators[0], $commission, $item->id, 'defavorable');
            $this->fail('Un membre ne peut pas voter deux fois pour le même dossier.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $outsider = User::factory()->create(['email' => 'unassigned-super@example.test', 'account_type' => 'super_admin', 'status' => 'active', 'email_verified_at' => now()]);
        try {
            $workflow->castVote($outsider, $commission, $item->id, 'abstention');
            $this->fail('Un super-administrateur non membre ne doit pas voter implicitement.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertDatabaseCount('commission_votes', 1);
        }
    }

    public function test_decision_requires_quorum_and_vote_then_separate_validation_and_validated_minutes(): void
    {
        [$manager, $evaluators, $commission, $applicationId] = $this->commissionContext();
        $validator = $this->admin('validator@example.test');
        $workflow = app(CommissionWorkflowService::class);
        $item = $commission->applications()->where('application_id', $applicationId)->firstOrFail();
        $workflow->schedule($manager, $commission);
        $workflow->sendConvocations($manager, $commission);
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $manager->id)->value('id'), 'present');
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $evaluators[0]->id)->value('id'), 'present');
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $evaluators[1]->id)->value('id'), 'absent');
        $workflow->startMeeting($manager, $commission);

        try {
            $workflow->proposeDecision($manager, $commission, $item->id, 'accepted', 'À financer.');
            $this->fail('Une délibération exige au moins un vote.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $workflow->castVote($evaluators[0], $commission, $item->id, 'favorable');
        $decision = $workflow->proposeDecision($evaluators[0], $commission, $item->id, 'accepted', 'À financer.');
        try {
            $workflow->validateDecision($evaluators[0], $commission, $decision->id);
            $this->fail('La personne qui propose ne peut pas valider sa propre décision.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->assertDatabaseHas('commission_decisions', ['id' => $decision->id, 'status' => 'pending_validation']);
        }

        $workflow->validateDecision($validator, $commission, $decision->id);
        try {
            $workflow->castVote($manager, $commission, $item->id, 'defavorable');
            $this->fail('Le vote d’un dossier est clos dès qu’une décision est proposée.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $workflow->saveMinutes($evaluators[0], $commission, 'Résumé des travaux', 'Délibération adoptée après examen des évaluations.');
        try {
            $workflow->validateMinutes($evaluators[0], $commission);
            $this->fail('L’auteur ne peut pas valider son propre procès-verbal.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->assertDatabaseHas('commission_minutes', ['commission_id' => $commission->id, 'status' => 'draft']);
        }
        $workflow->validateMinutes($validator, $commission);
        $workflow->completeMeeting($manager, $commission);

        try {
            $workflow->castVote($evaluators[0], $commission, $item->id, 'defavorable');
            $this->fail('Un vote ne peut pas être modifié ou réémis après clôture.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertDatabaseCount('commission_votes', 1);
        }

        $this->assertDatabaseHas('applications', ['id' => $applicationId, 'workflow_status' => ApplicationStatus::DECISION_MADE->value]);
        $this->assertDatabaseHas('application_results', ['application_id' => $applicationId, 'decision' => 'accepted']);
        $this->assertDatabaseHas('commission_decisions', ['id' => $decision->id, 'status' => 'validated', 'validated_by' => $validator->id]);
        $this->assertDatabaseHas('commission_minutes', ['commission_id' => $commission->id, 'status' => 'validated', 'validated_by' => $validator->id]);
        $this->assertDatabaseHas('commissions', ['id' => $commission->id, 'status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'commission.vote_cast', 'auditable_id' => $commission->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'commission.meeting_completed', 'auditable_id' => $commission->id]);
    }

    public function test_minutes_pdf_is_stored_privately_and_only_commission_members_can_download_it(): void
    {
        [$manager, $evaluators, $commission] = $this->commissionContext();
        $validator = $this->admin('minutes-validator@example.test');
        $workflow = app(CommissionWorkflowService::class);
        $workflow->schedule($manager, $commission);
        $workflow->sendConvocations($manager, $commission);
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $manager->id)->value('id'), 'present');
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $evaluators[0]->id)->value('id'), 'present');
        $workflow->markAttendance($manager, $commission, $commission->members()->where('user_id', $evaluators[1]->id)->value('id'), 'absent');
        $workflow->startMeeting($manager, $commission);
        $item = $commission->applications()->firstOrFail();
        $workflow->castVote($evaluators[0], $commission, $item->id, 'favorable');
        $decision = $workflow->proposeDecision($evaluators[0], $commission, $item->id, 'accepted', 'Décision approuvée.');
        $workflow->validateDecision($validator, $commission, $decision->id);
        Storage::disk('local')->put('commission-minutes/signed.pdf', '%PDF-private');
        $workflow->saveMinutes($manager, $commission, 'Résumé', 'Procès-verbal intégral.', 'commission-minutes/signed.pdf');
        $workflow->validateMinutes($validator, $commission);

        $this->actingAs($evaluators[0])->get(route('commissions.minutes.download', $commission))->assertOk();
        $this->actingAs($this->evaluator('outsider-download'))->get(route('commissions.minutes.download', $commission))->assertForbidden();
        $this->assertDatabaseHas('documents', ['id' => $commission->minutes()->value('document_id'), 'visibility' => 'private', 'disk' => 'local']);
    }

    private function commissionContext(): array
    {
        $manager = $this->admin();
        $evaluators = [$this->evaluator('member-a'), $this->evaluator('member-b')];
        $programId = (string) Str::uuid();
        $callId = (string) Str::uuid();
        DB::table('programs')->insert(['id' => $programId, 'name' => 'Programme commission', 'code' => 'COM-'.Str::random(8), 'type' => 'research', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('calls')->insert(['id' => $callId, 'program_id' => $programId, 'title' => 'Appel commission', 'reference' => 'CALL-COM-'.Str::random(6), 'opens_at' => today()->subDay(), 'closes_at' => today()->addWeek(), 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $commission = Commission::query()->create([
            'program_id' => $programId,
            'call_id' => $callId,
            'created_by' => $manager->id,
            'name' => 'Commission scientifique',
            'type' => 'research',
            'scheduled_at' => now()->addDay(),
            'venue' => 'Ouagadougou',
            'agenda' => 'Examen des candidatures.',
            'convocation_text' => 'Convocation officielle.',
            'quorum_percentage' => 50,
            'status' => 'draft',
        ]);
        $workflow = app(CommissionWorkflowService::class);
        $workflow->addMember($manager, $commission, $manager->id, 'president');
        $workflow->addMember($manager, $commission, $evaluators[0]->id, 'secretary');
        $workflow->addMember($manager, $commission, $evaluators[1]->id, 'rapporteur');

        $student = User::factory()->create(['account_type' => 'etudiant', 'status' => 'active']);
        $student->assignRole('etudiant');
        DB::table('student_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $student->id, 'inee' => 'INEE-'.Str::random(8), 'created_at' => now(), 'updated_at' => now()]);
        $applicationId = (string) Str::uuid();
        DB::table('applications')->insert([
            'id' => $applicationId, 'call_id' => $callId, 'program_id' => $programId, 'applicant_id' => $student->id,
            'reference' => 'APP-COM-'.Str::random(8), 'status' => 'decision', 'workflow_status' => ApplicationStatus::COMMISSION_REVIEW->value,
            'project_title' => 'Projet à délibérer', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('evaluation_criteria')->insert(['id' => (string) Str::uuid(), 'program_id' => $programId, 'name' => 'Pertinence', 'maximum_score' => 20, 'weight' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('required_documents')->insert(['id' => (string) Str::uuid(), 'program_id' => $programId, 'document_type' => 'attestation_recherche', 'label' => 'Attestation de recherche', 'is_required' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($evaluators as $evaluator) {
            DB::table('evaluations')->insert($this->evaluationRow($applicationId, $evaluator->id, 'validated'));
        }
        $workflow->attachApplication($manager, $commission, $applicationId, 'Dossier recevable.');

        return [$manager, $evaluators, $commission->fresh(), $applicationId];
    }

    private function evaluationRow(string $applicationId, int $evaluatorId, string $status): array
    {
        return ['id' => (string) Str::uuid(), 'application_id' => $applicationId, 'evaluator_id' => $evaluatorId, 'status' => $status, 'created_at' => now(), 'updated_at' => now()];
    }

    private function evaluator(string $email): User
    {
        $user = User::factory()->create(['email' => $email.'@example.test', 'account_type' => 'evaluateur', 'status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole('evaluateur');

        return $user;
    }

    private function admin(string $email = 'commission-admin@example.test'): User
    {
        $user = User::factory()->create(['email' => $email, 'account_type' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole('admin');

        return $user;
    }
}