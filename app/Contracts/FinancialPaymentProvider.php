<?php

namespace App\Contracts;

use App\Data\FinancialPaymentResult;
use App\Models\Payment;

interface FinancialPaymentProvider
{
    public function initiate(Payment $payment, array $context = []): FinancialPaymentResult;

    public function verify(string $externalReference): FinancialPaymentResult;
}