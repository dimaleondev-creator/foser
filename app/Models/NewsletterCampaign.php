<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsletterCampaign extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['created_by', 'title', 'subject', 'body', 'status', 'recipient_count', 'sent_count', 'failed_count', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            $campaign->created_by ??= auth()->id();
        });
    }
}
