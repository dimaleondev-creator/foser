<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FinancialPaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class FinancialReceiptController extends Controller
{
    public function show(string $receipt, string $token, FinancialPaymentService $payments): Response
    {
        $receiptRecord = $payments->receiptForToken($receipt, $token);
        abort_if(! $receiptRecord, 404);

        $payment = $receiptRecord->payment()->with('disbursement')->firstOrFail();
        $commitment = DB::table('financial_commitments')->where('id', $payment->disbursement->commitment_id)->first();
        $beneficiary = $payment->beneficiary_id ? User::query()->find($payment->beneficiary_id) : null;
        $program = $commitment?->program_id ? DB::table('programs')->where('id', $commitment->program_id)->value('name') : null;

        return Pdf::loadView('finance.receipt', compact('receiptRecord', 'payment', 'commitment', 'beneficiary', 'program'))
            ->setPaper('a4')
            ->stream($receiptRecord->receipt_number.'.pdf')
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }
}