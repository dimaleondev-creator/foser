<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudyLoan extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'study_loans';
    protected $fillable = ['program_id', 'name', 'maximum_amount', 'interest_rate', 'repayment_months', 'description', 'eligibility', 'beneficiaries', 'study_levels', 'required_documents', 'procedure', 'status'];
    protected function casts(): array { return ['maximum_amount' => 'decimal:2', 'interest_rate' => 'decimal:2']; }
    public function program() { return $this->belongsTo(Program::class); }
}
