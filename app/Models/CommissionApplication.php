<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CommissionApplication extends Model
{
    use HasUuids;

    protected $fillable = ['commission_id', 'application_id', 'added_by', 'observations'];

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function votes()
    {
        return $this->hasMany(CommissionVote::class);
    }

    public function decision()
    {
        return $this->hasOne(CommissionDecision::class);
    }
}