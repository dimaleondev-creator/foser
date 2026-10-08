<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CommissionVote extends Model
{
    use HasUuids;

    protected $fillable = ['commission_id', 'commission_application_id', 'commission_member_id', 'vote', 'comment', 'voted_at'];

    protected function casts(): array
    {
        return ['voted_at' => 'datetime'];
    }

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    public function commissionApplication()
    {
        return $this->belongsTo(CommissionApplication::class);
    }

    public function member()
    {
        return $this->belongsTo(CommissionMember::class, 'commission_member_id');
    }
}