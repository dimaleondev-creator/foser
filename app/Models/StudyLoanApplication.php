<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudyLoanApplication extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['applicant_id', 'study_loan_id', 'reference', 'amount', 'duration_months', 'interest_rate', 'grace_period_months', 'monthly_payment', 'total_interest', 'total_repayment', 'first_due_at', 'last_due_at', 'simulation', 'status', 'decision_note'];
    protected function casts(): array { return ['amount'=>'decimal:2','interest_rate'=>'decimal:2','monthly_payment'=>'decimal:2','total_interest'=>'decimal:2','total_repayment'=>'decimal:2','first_due_at'=>'date','last_due_at'=>'date','simulation'=>'array']; }
    public function loan() { return $this->belongsTo(StudyLoan::class, 'study_loan_id'); }
    public function applicant() { return $this->belongsTo(User::class, 'applicant_id'); }
    public function installments() { return $this->hasMany(StudyLoanInstallment::class); }
}
