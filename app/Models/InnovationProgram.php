<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InnovationProgram extends Model
{
    use HasUuids;

    protected $table = 'innovation_programs';
    protected $fillable = ['program_id', 'innovation_area', 'target_stage', 'innovation_type', 'eligibility', 'support', 'duration', 'procedure', 'description', 'objectives', 'target_audience', 'phases', 'training', 'mentoring', 'technical_support', 'entrepreneurial_support', 'partners', 'resources', 'calendar', 'contact', 'prizes', 'opens_at', 'closes_at', 'proclamation_at', 'technology', 'results', 'patents', 'status'];
    public function program() { return $this->belongsTo(Program::class); }
}
