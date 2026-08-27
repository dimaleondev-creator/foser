<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['name', 'slug', 'logo_path', 'description', 'type', 'category', 'website_url', 'email', 'phone', 'sort_order', 'status', 'is_featured', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'starts_at' => 'date', 'ends_at' => 'date', 'sort_order' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
