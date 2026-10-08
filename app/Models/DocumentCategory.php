<?php

namespace App\Models;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentCategory extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'document_categories';
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::created(fn (self $category) => app(AuditLogger::class)->record('created', self::class, (string) $category->getKey(), [], $category->getAttributes()));
        static::updated(fn (self $category) => app(AuditLogger::class)->record('updated', self::class, (string) $category->getKey(), $category->getOriginal(), $category->getChanges()));
        static::deleted(fn (self $category) => app(AuditLogger::class)->record('deleted', self::class, (string) $category->getKey(), $category->getOriginal()));
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'category_id');
    }
}
