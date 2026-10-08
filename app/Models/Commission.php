<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasUuids;

    protected $fillable = [
        'program_id', 'call_id', 'created_by', 'name', 'type', 'description', 'scheduled_at',
        'venue', 'agenda', 'convocation_text', 'convocation_sent_at', 'quorum_percentage',
        'status', 'started_at', 'completed_at', 'cancelled_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'convocation_sent_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
            'quorum_percentage' => 'integer',
        ];
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function call()
    {
        return $this->belongsTo(Call::class);
    }

    public function members()
    {
        return $this->hasMany(CommissionMember::class);
    }

    public function applications()
    {
        return $this->hasMany(CommissionApplication::class);
    }

    public function votes()
    {
        return $this->hasMany(CommissionVote::class);
    }

    public function decisions()
    {
        return $this->hasMany(CommissionDecision::class);
    }

    public function minutes()
    {
        return $this->hasOne(CommissionMinutes::class);
    }
}