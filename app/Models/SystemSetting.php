<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
	use HasUuids;

	protected $table = 'system_settings';
	protected $fillable = ['key', 'value', 'type', 'is_public'];

	protected function casts(): array
	{
		return ['is_public' => 'boolean'];
	}

	protected static function booted(): void
	{
		static::saved(fn () => Cache::forget('public_site_logo_path'));
		static::deleted(fn () => Cache::forget('public_site_logo_path'));
	}
}
