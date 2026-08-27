<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ResearchProgram extends Model
{
    use HasUuids;

    protected $table = 'research_programs';
    protected $fillable = ['program_id', 'research_area', 'maturity_level', 'beneficiaries', 'conditions', 'maximum_amount', 'minimum_amount', 'duration', 'establishments', 'calendar', 'opens_at', 'closes_at', 'contact', 'document_path', 'description', 'status'];
    public function program() { return $this->belongsTo(Program::class); }
}
