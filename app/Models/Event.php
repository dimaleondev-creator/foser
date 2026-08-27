<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
	use HasUuids, SoftDeletes;

	protected $fillable = [
		'organizer_id', 'category', 'title', 'slug', 'description', 'venue',
		'starts_at', 'ends_at', 'image_path', 'external_url', 'status',
		'is_featured', 'capacity', 'registration_url',
	];

	protected function casts(): array
	{
		return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_featured' => 'boolean', 'capacity' => 'integer'];
	}

	public function scopePublished(Builder $query): Builder
	{
		return $query->where('status', 'published');
	}

	public function scopeUpcoming(Builder $query): Builder
	{
		return $query->where(function (Builder $builder): void {
			$builder->where('starts_at', '>=', now())->orWhere('ends_at', '>=', now());
		});
	}

	public function scopePast(Builder $query): Builder
	{
		return $query->whereNotNull('ends_at')->where('ends_at', '<', now());
	}

	public function getRouteKeyName(): string
	{
		return 'slug';
	}
}
