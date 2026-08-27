<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationStatus;
use App\Models\FinancialCommitment;
use App\Models\User;
use App\Services\FinancialWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');
    }

    public function test_financial_operation_can_be_validated_and_audited(): void
    {
        $actor = User::factory()->create(['account_type' => 'agent_finance']);
        $actor->assignRole('agent_finance');
        $commitment = FinancialCommitment::create(['reference' => 'ENG-TEST', 'amount' => 1000, 'currency' => 'XOF', 'status' => 'soumis', 'committed_at' => today()]);

        app(FinancialWorkflow::class)->transition('financial_commitments', $commitment->id, FinancialOperationStatus::VALIDE, $actor, 'Contrôle effectué.');

        $this->assertDatabaseHas('financial_commitments', ['id' => $commitment->id, 'status' => 'valide', 'processed_by' => $actor->id]);
        $this->assertDatabaseHas('financial_audit_logs', ['operation_id' => $commitment->id, 'event' => 'financial.status_changed']);
    }

    public function test_validated_financial_operation_cannot_be_deleted(): void
    {
        $commitment = FinancialCommitment::create(['reference' => 'ENG-LOCK', 'amount' => 1000, 'currency' => 'XOF', 'status' => 'valide', 'committed_at' => today()]);

        $this->expectException(\LogicException::class);
        $commitment->delete();
    }
}
