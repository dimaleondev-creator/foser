<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FinancialReconciliation extends Model
{
    use HasUuids;

    protected $fillable = ['payment_id', 'expected_amount', 'paid_amount', 'received_amount', 'external_reference', 'status', 'notes', 'reconciled_by', 'reconciled_at'];

    protected function casts(): array
    {
        return ['expected_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'received_amount' => 'decimal:2', 'reconciled_at' => 'datetime'];
    }
}