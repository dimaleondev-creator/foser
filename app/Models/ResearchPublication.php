<?php

namespace App\Models;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResearchPublication extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'research_publications';
    protected $fillable = ['research_project_id', 'author_id', 'title', 'doi', 'publication_type', 'published_on', 'status', 'document_id'];
    protected $casts = ['published_on' => 'date'];

    protected static function booted(): void
    {
        static::created(fn (self $publication) => app(AuditLogger::class)->record('created', self::class, (string) $publication->getKey(), [], $publication->getAttributes()));
        static::updated(fn (self $publication) => app(AuditLogger::class)->record('updated', self::class, (string) $publication->getKey(), $publication->getOriginal(), $publication->getChanges()));
        static::deleted(fn (self $publication) => app(AuditLogger::class)->record('deleted', self::class, (string) $publication->getKey(), $publication->getOriginal()));
    }

    public function project()
    {
        return $this->belongsTo(ResearchProject::class, 'research_project_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
