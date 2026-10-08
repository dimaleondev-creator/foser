<?php

namespace App\Models;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $fillable = ['document_id', 'user_id', 'ip_address', 'downloaded_at'];
    protected $casts = ['downloaded_at' => 'datetime'];

    protected static function booted(): void
    {
        static::created(fn (self $download) => app(AuditLogger::class)->record('created', self::class, (string) $download->getKey(), [], $download->getAttributes()));
        static::updated(fn (self $download) => app(AuditLogger::class)->record('updated', self::class, (string) $download->getKey(), $download->getOriginal(), $download->getChanges()));
        static::deleted(fn (self $download) => app(AuditLogger::class)->record('deleted', self::class, (string) $download->getKey(), $download->getOriginal()));
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
