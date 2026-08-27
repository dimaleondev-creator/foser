<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class InstitutionValue extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'institution_values';
    protected $fillable = ['name', 'description', 'icon', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }
}