<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StudyLoanInstallment extends Model
{
    use HasUuids;

    protected $fillable = ['study_loan_application_id', 'installment_number', 'due_at', 'principal_amount', 'interest_amount', 'amount', 'status', 'paid_at'];
    protected function casts(): array { return ['due_at'=>'date','paid_at'=>'datetime','principal_amount'=>'decimal:2','interest_amount'=>'decimal:2','amount'=>'decimal:2']; }
    public function application() { return $this->belongsTo(StudyLoanApplication::class, 'study_loan_application_id'); }
}
