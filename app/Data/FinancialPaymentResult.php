<?php

namespace App\Data;

use App\Enums\PaymentStatus;

final readonly class FinancialPaymentResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $externalReference = null,
        public array $metadata = [],
    ) {}
}