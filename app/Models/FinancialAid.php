<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialAid extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'financial_aids';
    protected $fillable = ['program_id', 'name', 'aid_type', 'maximum_amount', 'frequency', 'description', 'eligibility', 'beneficiaries', 'required_documents', 'conditions', 'procedure', 'processing_time', 'status'];
    protected function casts(): array { return ['maximum_amount' => 'decimal:2']; }
    public function program() { return $this->belongsTo(Program::class); }
}
