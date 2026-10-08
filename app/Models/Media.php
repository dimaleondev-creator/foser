<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{
	use HasUuids, SoftDeletes;

	protected $table = 'media';
	protected $fillable = ['album_id', 'uploaded_by', 'title', 'media_type', 'disk', 'path', 'external_url', 'mime_type', 'size', 'status'];

	protected static function booted(): void
	{
		static::creating(function (self $media): void {
			$media->uploaded_by ??= auth()->id();
		});

		static::saving(function (self $media): void {
			if (filled($media->external_url) && blank($media->path)) {
				$media->disk = 'public';
				$media->path = $media->external_url;
				$media->media_type = 'video';
			}
		});
	}
}
