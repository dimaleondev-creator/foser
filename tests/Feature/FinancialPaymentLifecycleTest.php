<?php

namespace Tests\Feature;

use App\Models\Disbursement;
use App\Models\FinancialCommitment;
use App\Models\Payment;
use App\Models\User;
use App\Services\FinancialPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialPaymentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');
    }

    public function test_payment_creation_is_idempotent_and_records_the_initiator(): void
    {
        [$actor, $disbursement] = $this->financialSetup();
        $service = app(FinancialPaymentService::class);
        $data = ['amount' => 600, 'payment_method' => 'bank_transfer'];

        $first = $service->initiate($disbursement, $actor, $data, 'request-unique-1');
        $retry = $service->initiate($disbursement, $actor, $data, 'request-unique-1');

        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, DB::table('payment_records')->count());
        $this->assertDatabaseHas('financial_transactions', ['payment_id' => $first->id, 'initiated_by' => $actor->id, 'status' => 'pending']);
    }

    public function test_payment_reservations_cannot_exceed_the_disbursement(): void
    {
        [$actor, $disbursement] = $this->financialSetup();
        $service = app(FinancialPaymentService::class);
        $service->initiate($disbursement, $actor, ['amount' => 700], 'request-reserve-1');

        $this->expectException(ValidationException::class);
        $service->initiate($disbursement, $actor, ['amount' => 400], 'request-reserve-2');
    }

    public function test_confirmed_payment_is_immutable_and_issues_a_secure_receipt(): void
    {
        [$actor, $disbursement] = $this->financialSetup();
        $service = app(FinancialPaymentService::class);
        $payment = $service->initiate($disbursement, $actor, ['amount' => 1000, 'reference' => 'BANK-001'], 'request-paid-1');

        $receiptUrl = $service->transition($payment, 'paid', $actor, 'BANK-001');
        $receipt = DB::table('financial_receipts')->where('payment_id', $payment->id)->first();

        $this->assertNotNull($receiptUrl);
        $token = basename((string) parse_url($receiptUrl, PHP_URL_PATH));
        $this->assertNotNull($service->receiptForToken($receipt->id, $token));
        $this->get(route('finance.receipts.show', ['receipt' => $receipt->id, 'token' => $token]))->assertOk();
        $this->get(route('finance.receipts.show', ['receipt' => $receipt->id, 'token' => 'invalid']))->assertNotFound();
        $this->assertDatabaseHas('financial_transactions', ['payment_id' => $payment->id, 'status' => 'paid', 'external_reference' => 'BANK-001']);
        $this->assertDatabaseHas('financial_audit_logs', ['operation_id' => $payment->id, 'event' => 'payment.receipt_issued']);

        $this->expectException(\LogicException::class);
        $payment->refresh()->fill(['amount' => 1])->save();
    }

    public function test_failed_and_cancelled_payments_are_recorded_as_distinct_statuses(): void
    {
        [$actor, $disbursement] = $this->financialSetup();
        $service = app(FinancialPaymentService::class);
        $failed = $service->initiate($disbursement, $actor, ['amount' => 400], 'request-failed-1');
        $cancelled = $service->initiate($disbursement, $actor, ['amount' => 600], 'request-cancelled-1');

        $service->transition($failed, 'failed', $actor, 'BANK-FAIL');
        $service->transition($cancelled, 'cancelled', $actor);
        $reversed = $service->initiate($disbursement, $actor, ['amount' => 1000], 'request-reversed-1');
        $service->transition($reversed, 'paid', $actor, 'BANK-REVERSE');
        $service->transition($reversed, 'reversed', $actor, 'BANK-REVERSE');

        $this->assertDatabaseHas('payment_records', ['id' => $failed->id, 'status' => 'failed']);
        $this->assertDatabaseHas('payment_records', ['id' => $cancelled->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('payment_records', ['id' => $reversed->id, 'status' => 'reversed']);
        $this->assertDatabaseHas('disbursements', ['id' => $disbursement->id, 'status' => 'planned']);
        $this->assertDatabaseHas('financial_transactions', ['payment_id' => $failed->id, 'status' => 'failed']);
    }

    public function test_authorization_and_execution_permissions_are_independent(): void
    {
        [, $disbursement] = $this->financialSetup();
        $authorizer = User::factory()->create(['account_type' => 'etudiant']);
        $authorizer->givePermissionTo('finance.authorize');
        $executor = User::factory()->create(['account_type' => 'etudiant']);
        $executor->givePermissionTo('finance.execute');
        $service = app(FinancialPaymentService::class);
        $payment = $service->initiate($disbursement, $authorizer, ['amount' => 1000], 'request-permissions-1');

        try {
            $service->transition($payment, 'paid', $authorizer);
            $this->fail('Un autorisateur ne peut pas exécuter le paiement.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $service->transition($payment, 'paid', $executor);
        $this->assertDatabaseHas('payment_records', ['id' => $payment->id, 'status' => 'paid']);
    }

    public function test_api_payment_routes_enforce_the_separate_authorize_and_execute_permissions(): void
    {
        [, $disbursement] = $this->financialSetup();
        $authorizer = User::factory()->create(['account_type' => 'etudiant']);
        $authorizer->givePermissionTo('finance.authorize');
        $executor = User::factory()->create(['account_type' => 'etudiant']);
        $executor->givePermissionTo('finance.execute');

        $paymentId = $this->actingAs($authorizer, 'sanctum')
            ->withHeader('Idempotency-Key', 'api-finance-req-1')
            ->postJson('/api/v1/disbursements/'.$disbursement->id.'/payments', ['amount' => 1000])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($authorizer, 'sanctum')
            ->postJson('/api/v1/payments/'.$paymentId.'/transition', ['status' => 'paid'])
            ->assertForbidden();

        $this->actingAs($executor, 'sanctum')
            ->postJson('/api/v1/payments/'.$paymentId.'/transition', ['status' => 'paid', 'external_reference' => 'BANK-API-1'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonStructure(['receipt_url']);
    }

    public function test_only_reconciliation_permission_can_compare_confirmed_amounts(): void
    {
        [$actor, $disbursement] = $this->financialSetup();
        $service = app(FinancialPaymentService::class);
        $payment = $service->initiate($disbursement, $actor, ['amount' => 1000], 'request-reconcile-1');
        $service->transition($payment, 'paid', $actor, 'BANK-REC-1');

        $matched = $service->reconcile($payment, ['paid_amount' => 1000, 'received_amount' => 1000, 'external_reference' => 'BANK-REC-1'], $actor);
        $this->assertSame('matched', $matched->status);
        $discrepancy = $service->reconcile($payment, ['paid_amount' => 1000, 'received_amount' => 990, 'external_reference' => 'BANK-REC-1'], $actor);
        $this->assertSame('discrepancy', $discrepancy->status);

        $ordinaryUser = User::factory()->create(['account_type' => 'etudiant']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->reconcile($payment, ['paid_amount' => 1000, 'received_amount' => 990], $ordinaryUser);
    }

    private function financialSetup(): array
    {
        $beneficiary = User::factory()->create(['account_type' => 'etudiant']);
        $actor = User::factory()->create(['account_type' => 'agent_finance']);
        $commitment = FinancialCommitment::query()->create([
            'beneficiary_id' => $beneficiary->id,
            'reference' => 'ENG-'.fake()->unique()->numerify('#####'),
            'amount' => 1000,
            'budget' => 1000,
            'currency' => 'XOF',
            'fiscal_year' => today()->year,
            'status' => 'valide',
            'committed_at' => today(),
        ]);
        $disbursement = Disbursement::query()->create([
            'commitment_id' => $commitment->id,
            'reference' => 'DEC-'.fake()->unique()->numerify('#####'),
            'amount' => 1000,
            'installment_number' => 1,
            'status' => 'planned',
        ]);

        return [$actor, $disbursement];
    }
}