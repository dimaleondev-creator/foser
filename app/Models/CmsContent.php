<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CmsContent extends Model
{
    use HasUuids;

    protected $table = 'cms_contents';
    protected $fillable = ['content_key', 'locale', 'title', 'summary', 'body', 'payload', 'status', 'published_at', 'author_id'];
    protected function casts(): array { return ['payload' => 'array', 'published_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::creating(function (self $content): void {
            $content->author_id ??= auth()->id();
        });
    }

    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}
