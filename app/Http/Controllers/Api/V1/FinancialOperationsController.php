<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Disbursement;
use App\Models\Payment;
use App\Models\User;
use App\Services\FinancialPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialOperationsController extends ApiController
{
    public function initiate(Request $request, Disbursement $disbursement, FinancialPaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['nullable', 'string', 'max:40'],
        ]);
        $key = $request->header('Idempotency-Key');
        abort_if(blank($key), 422, 'L’en-tête Idempotency-Key est obligatoire.');

        $payment = $payments->initiate($disbursement, $this->actor($request), $data, $key);
        return response()->json(['data' => $payment->only(['id', 'disbursement_id', 'beneficiary_id', 'provider_reference', 'payment_method', 'amount', 'status', 'paid_at'])], 201);
    }

    public function transition(Request $request, Payment $payment, FinancialPaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:processing,paid,failed,cancelled,reversed'],
            'external_reference' => ['nullable', 'string', 'max:160'],
            'metadata' => ['nullable', 'array'],
        ]);
        $receiptUrl = $payments->transition($payment, $data['status'], $this->actor($request), $data['external_reference'] ?? null, $data['metadata'] ?? []);

        return response()->json(['data' => $payment->fresh()->only(['id', 'disbursement_id', 'beneficiary_id', 'provider_reference', 'payment_method', 'amount', 'status', 'paid_at']), 'receipt_url' => $receiptUrl]);
    }

    public function reconcile(Request $request, Payment $payment, FinancialPaymentService $payments): JsonResponse
    {
        $data = $request->validate([
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'received_amount' => ['required', 'numeric', 'min:0'],
            'external_reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json(['data' => $payments->reconcile($payment, $data, $this->actor($request))], 201);
    }

    public function transactions(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        return response()->json(DB::table('financial_transactions')->orderByDesc('transaction_at')->paginate($perPage));
    }

    public function reconciliations(Request $request, Payment $payment): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        return response()->json(DB::table('financial_reconciliations')->where('payment_id', $payment->id)->orderByDesc('reconciled_at')->paginate($perPage));
    }

    public function history(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        return response()->json(DB::table('financial_audit_logs')->orderByDesc('created_at')->paginate($perPage));
    }

    public function uploadDisbursementDocument(Request $request, Disbursement $disbursement): JsonResponse
    {
        $request->validate(['document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
        $actor = $this->actor($request);
        abort_unless($actor->can('finance.authorize') || $actor->can('finance.manage'), 403);
        $path = $request->file('document')->store('finance/disbursements/'.$disbursement->id, 'local');
        DB::table('disbursements')->where('id', $disbursement->id)->update(['supporting_document_path' => $path, 'updated_at' => now()]);
        DB::table('financial_audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $actor->id,
            'operation_type' => 'disbursements',
            'operation_id' => $disbursement->id,
            'event' => 'financial.disbursement_document_uploaded',
            'old_values' => null,
            'new_values' => json_encode(['path' => $path]),
            'created_at' => now(),
        ]);

        return response()->json(['data' => ['disbursement_id' => $disbursement->id, 'document_attached' => true]], 201);
    }

    private function actor(Request $request): User
    {
        abort_unless($request->user() instanceof User, 401);
        return $request->user();
    }
}