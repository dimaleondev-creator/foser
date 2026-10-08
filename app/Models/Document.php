<?php

namespace App\Models;

use App\Observers\DocumentObserver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
	use HasUuids, SoftDeletes;

	protected $fillable = ['category_id', 'uploaded_by', 'title', 'description', 'keywords', 'year', 'author', 'document_type', 'reference', 'document_date', 'version_label', 'disk', 'path', 'mime_type', 'size', 'checksum', 'visibility', 'language', 'status', 'published_at'];

	protected $attributes = ['disk' => 'private', 'visibility' => 'private', 'language' => 'fr', 'status' => 'draft'];

	protected function casts(): array
	{
		return ['published_at' => 'datetime', 'document_date' => 'date', 'year' => 'integer', 'size' => 'integer'];
	}

	protected static function booted(): void
	{
		static::observe(DocumentObserver::class);
	}

	public function uploader()
	{
		return $this->belongsTo(User::class, 'uploaded_by');
	}

	public function category()
	{
		return $this->belongsTo(DocumentCategory::class, 'category_id');
	}

	public function downloads()
	{
		return $this->hasMany(Download::class);
	}

	public function versions()
	{
		return $this->hasMany(DocumentVersion::class)->orderBy('version');
	}
}
