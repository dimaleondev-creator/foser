<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\FinancialReconciliation;
use App\Models\FinancialReceipt;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialPaymentService
{
    private const TRANSITIONS = [
        'pending' => ['processing', 'paid', 'failed', 'cancelled'],
        'processing' => ['paid', 'failed', 'cancelled'],
        'paid' => ['reversed'],
        'failed' => [],
        'cancelled' => [],
        'reversed' => [],
    ];

    public function initiate(Disbursement $disbursement, User $actor, array $data, string $idempotencyKey): Payment
    {
        abort_unless($actor->can('finance.authorize') || $actor->can('finance.manage'), 403);
        if (blank($idempotencyKey) || strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'Une clé d’idempotence valide est obligatoire.']);
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être supérieur à zéro.']);
        }

        return DB::transaction(function () use ($disbursement, $actor, $data, $idempotencyKey, $amount): Payment {
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $this->assertSameRequest($existing, $disbursement, $amount, $data['payment_method'] ?? null, $data['reference'] ?? null);
                return $existing;
            }

            $lockedDisbursement = Disbursement::query()->lockForUpdate()->findOrFail($disbursement->id);
            $commitment = DB::table('financial_commitments')->where('id', $lockedDisbursement->commitment_id)->first();
            $beneficiaryId = $commitment->beneficiary_id ?? null;
            $reserved = (float) Payment::query()->where('disbursement_id', $lockedDisbursement->id)
                ->whereIn('status', ['pending', 'processing', 'paid'])->sum('amount');

            if ($amount + $reserved > (float) $lockedDisbursement->amount) {
                throw ValidationException::withMessages(['amount' => 'Le montant dépasse le solde disponible du décaissement.']);
            }

            $payment = Payment::query()->create([
                'disbursement_id' => $lockedDisbursement->id,
                'beneficiary_id' => $beneficiaryId,
                'provider_reference' => $data['reference'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'payment_method' => $data['payment_method'] ?? null,
                'amount' => $amount,
                'status' => 'pending',
                'initiated_by' => $actor->id,
            ]);

            $this->recordTransaction($payment, $actor, 'payment_initiated', 'pending', ['method' => $payment->payment_method], $idempotencyKey.':initiated');
            $this->audit($actor, 'payment.initiated', $payment->id, [], ['amount' => $amount, 'status' => 'pending']);

            return $payment;
        });
    }

    public function transition(Payment|string $payment, string $nextStatus, User $actor, ?string $externalReference = null, array $metadata = []): ?string
    {
        $paymentId = $payment instanceof Payment ? $payment->id : $payment;
        abort_unless(in_array($nextStatus, ['processing', 'paid', 'failed', 'cancelled', 'reversed'], true), 422);
        $permission = in_array($nextStatus, ['cancelled', 'reversed'], true) ? 'finance.authorize' : 'finance.execute';
        abort_unless($actor->can($permission) || $actor->can('finance.manage'), 403);

        return DB::transaction(function () use ($paymentId, $nextStatus, $actor, $externalReference, $metadata): ?string {
            $record = DB::table('payment_records')->where('id', $paymentId)->lockForUpdate()->firstOrFail();
            if (! in_array($nextStatus, self::TRANSITIONS[$record->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Cette transition de paiement n’est pas autorisée.']);
            }

            $now = now();
            $values = ['status' => $nextStatus, 'processed_by' => $actor->id, 'updated_at' => $now];
            if ($nextStatus === 'paid') {
                $values['paid_at'] = $now;
                $values['executed_at'] = $now;
            }
            if ($nextStatus === 'cancelled') $values['cancelled_at'] = $now;
            DB::table('payment_records')->where('id', $paymentId)->update($values);

            $this->recordTransaction(Payment::query()->findOrFail($paymentId), $actor, 'payment_status_changed', $nextStatus, $metadata, 'payment:'.$paymentId.':'.$nextStatus, $externalReference);
            $this->audit($actor, 'payment.status_changed', $paymentId, ['status' => $record->status], ['status' => $nextStatus, 'external_reference' => $externalReference]);

            if (in_array($nextStatus, ['paid', 'reversed'], true)) {
                $disbursement = DB::table('disbursements')->where('id', $record->disbursement_id)->first();
                $paidTotal = (float) DB::table('payment_records')->where('disbursement_id', $record->disbursement_id)->where('status', 'paid')->sum('amount');
                $fullyDisbursed = $paidTotal >= (float) $disbursement->amount;
                DB::table('disbursements')->where('id', $record->disbursement_id)->update([
                    'status' => $fullyDisbursed ? 'execute' : 'planned',
                    'disbursed_at' => $fullyDisbursed ? today() : null,
                    'executed_at' => $fullyDisbursed ? $now : null,
                    'processed_by' => $actor->id,
                    'updated_at' => $now,
                ]);
                $this->audit($actor, 'disbursement.status_changed', $record->disbursement_id, ['status' => $disbursement->status], ['status' => $fullyDisbursed ? 'execute' : 'planned']);
            }

            if ($nextStatus === 'paid') {
                $token = Str::random(64);
                $receipt = FinancialReceipt::query()->create([
                    'payment_id' => $paymentId,
                    'receipt_number' => 'REC-'.strtoupper(Str::random(12)),
                    'token_hash' => hash('sha256', $token),
                    'issued_at' => $now,
                ]);
                $this->audit($actor, 'payment.receipt_issued', $paymentId, [], ['issued_at' => $now->toISOString()]);
                app(DashboardStatisticsService::class)->invalidate();
                return route('finance.receipts.show', ['receipt' => $receipt->id, 'token' => $token]);
            }

            app(DashboardStatisticsService::class)->invalidate();
            return null;
        });
    }

    public function reconcile(Payment|string $payment, array $data, User $actor): FinancialReconciliation
    {
        abort_unless($actor->can('finance.reconcile') || $actor->can('finance.manage'), 403);
        $paymentId = $payment instanceof Payment ? $payment->id : $payment;

        return DB::transaction(function () use ($paymentId, $data, $actor): FinancialReconciliation {
            $record = Payment::query()->lockForUpdate()->findOrFail($paymentId);
            if ($record->status !== 'paid') {
                throw ValidationException::withMessages(['payment' => 'Seul un paiement confirmé peut être rapproché.']);
            }
            $paidAmount = round((float) ($data['paid_amount'] ?? 0), 2);
            $receivedAmount = round((float) ($data['received_amount'] ?? 0), 2);
            $expectedAmount = (float) $record->amount;
            $status = abs($expectedAmount - $paidAmount) < 0.01 && abs($paidAmount - $receivedAmount) < 0.01 ? 'matched' : 'discrepancy';

            $reconciliation = FinancialReconciliation::query()->create([
                'payment_id' => $record->id,
                'expected_amount' => $expectedAmount,
                'paid_amount' => $paidAmount,
                'received_amount' => $receivedAmount,
                'external_reference' => $data['external_reference'] ?? null,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'reconciled_by' => $actor->id,
                'reconciled_at' => now(),
            ]);

            $this->audit($actor, 'payment.reconciled', $record->id, [], ['reconciliation_id' => $reconciliation->id, 'status' => $status]);
            $this->recordTransaction($record, $actor, 'payment_reconciled', $status, ['expected_amount' => $expectedAmount, 'paid_amount' => $paidAmount, 'received_amount' => $receivedAmount], 'reconciliation:'.$reconciliation->id, $data['external_reference'] ?? null);
            return $reconciliation;
        });
    }

    public function receiptForToken(string $receiptId, string $token): ?FinancialReceipt
    {
        $receipt = FinancialReceipt::query()->with('payment')->find($receiptId);
        if (! $receipt || $receipt->revoked_at || ! hash_equals($receipt->token_hash, hash('sha256', $token)) || $receipt->payment?->status !== 'paid') {
            return null;
        }

        return $receipt;
    }

    private function can(User $actor, string $permission): bool
    {
        return $actor->can($permission) || $actor->can('finance.manage');
    }

    private function assertSameRequest(Payment $existing, Disbursement $disbursement, float $amount, ?string $method, ?string $reference): void
    {
        if ($existing->disbursement_id !== $disbursement->id || (float) $existing->amount !== $amount || $existing->payment_method !== $method || $existing->provider_reference !== $reference) {
            throw ValidationException::withMessages(['idempotency_key' => 'Cette clé d’idempotence est déjà utilisée pour une autre opération.']);
        }
    }

    private function recordTransaction(Payment $payment, User $actor, string $type, string $status, array $metadata, string $idempotencyKey, ?string $externalReference = null): void
    {
        DB::table('financial_transactions')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'payment_id' => $payment->id,
            'reference' => 'TRX-'.strtoupper(Str::random(16)),
            'beneficiary_id' => $payment->beneficiary_id,
            'amount' => $payment->amount,
            'type' => $type,
            'status' => $status,
            'transaction_at' => now(),
            'metadata' => json_encode($metadata),
            'initiated_by' => $actor->id,
            'external_reference' => $externalReference,
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function audit(User $actor, string $event, string $paymentId, array $oldValues, array $newValues): void
    {
        DB::table('financial_audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $actor->id,
            'operation_type' => 'payment_records',
            'operation_id' => $paymentId,
            'event' => $event,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'created_at' => now(),
        ]);
    }
}