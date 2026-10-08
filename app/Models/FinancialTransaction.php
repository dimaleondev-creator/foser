<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    use HasUuids;

    protected $fillable = ['payment_id', 'reference', 'beneficiary_id', 'amount', 'type', 'status', 'transaction_at', 'metadata', 'initiated_by', 'external_reference', 'idempotency_key'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_at' => 'datetime', 'metadata' => 'array'];
    }
}