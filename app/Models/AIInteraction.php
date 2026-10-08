<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AIInteraction extends Model
{
    use HasUuids;

    protected $table = 'ai_interactions';
    protected $fillable = ['user_id', 'session_id', 'provider', 'model', 'feature', 'question', 'response', 'sources', 'risk_level', 'latency_ms', 'status', 'feedback', 'feedback_comment'];
    protected function casts(): array
    {
        return ['sources' => 'array', 'feedback' => 'integer'];
    }
}
