<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CommissionMinutes extends Model
{
    use HasUuids;

    protected $table = 'commission_minutes';
    protected $fillable = [
        'commission_id', 'document_id', 'authored_by', 'validated_by', 'summary', 'content',
        'status', 'validated_at',
    ];

    protected function casts(): array
    {
        return ['validated_at' => 'datetime'];
    }

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}