<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CommissionDecision extends Model
{
    use HasUuids;

    protected $fillable = [
        'commission_id', 'commission_application_id', 'decided_by', 'validated_by',
        'decision', 'status', 'justification', 'decided_at', 'validated_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'validated_at' => 'datetime'];
    }

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    public function commissionApplication()
    {
        return $this->belongsTo(CommissionApplication::class);
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}