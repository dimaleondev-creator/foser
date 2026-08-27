<?php

namespace App\Http\Resources;

class PaymentResource extends ApiResource
{
	protected array $fields = ['id', 'disbursement_id', 'beneficiary_id', 'provider_reference', 'payment_method', 'amount', 'status', 'paid_at', 'processed_by', 'executed_at', 'cancelled_at', 'created_at', 'updated_at'];
}
