<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\PaymentResource;
use App\Models\Payment;

class PaymentController extends ApiController
{
    protected string $model = Payment::class;
    protected string $resource = PaymentResource::class;
    protected array $searchable = ['provider_reference', 'payment_method', 'status'];
    protected array $filterable = ['disbursement_id', 'beneficiary_id', 'status'];
    protected array $sortable = ['paid_at', 'amount', 'created_at'];
}
