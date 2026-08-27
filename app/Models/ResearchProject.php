<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResearchProject extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'research_projects';
    protected $fillable = ['research_program_id', 'laboratory_id', 'principal_researcher_id', 'title', 'reference', 'abstract', 'description', 'expected_results', 'achieved_results', 'domain', 'year', 'budget', 'funded_amount', 'currency', 'starts_at', 'ends_at', 'contact', 'status'];
    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'budget' => 'decimal:2'];
    }
}
