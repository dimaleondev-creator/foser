<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectorMessage extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'director_messages';

    protected $fillable = [
        'full_name',
        'position',
        'photo_path',
        'message',
        'signature',
        'status',
        'published_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
