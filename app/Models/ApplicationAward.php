<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApplicationAward extends Model
{
    use HasUuids;

    protected $table = 'application_awards';
    protected $fillable = ['application_id', 'beneficiary_id', 'program_id', 'amount', 'award_date', 'decision_reference', 'status'];
    protected function casts(): array { return ['award_date' => 'date', 'amount' => 'decimal:2']; }
}