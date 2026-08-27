<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsletterSubscriber extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'newsletter_subscribers';

    protected $fillable = [
        'name',
        'email',
        'status',
        'source',
        'confirmation_token_hash', 'confirmation_expires_at', 'confirmed_at', 'unsubscribe_token_hash',
    ];

    protected function casts(): array { return ['confirmation_expires_at' => 'datetime', 'confirmed_at' => 'datetime']; }
}
