<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomeSlider extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'home_sliders';

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'image_path',
        'button_label',
        'button_url',
        'is_active',
        'sort_order',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
