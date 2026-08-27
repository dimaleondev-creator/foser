<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Testimonial extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['first_name', 'last_name', 'job_title', 'organization', 'photo_path', 'body', 'program_id', 'rating', 'status', 'sort_order', 'published_at', 'consent_given'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'sort_order' => 'integer', 'published_at' => 'datetime', 'consent_given' => 'boolean'];
    }

    public function getDisplayNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
