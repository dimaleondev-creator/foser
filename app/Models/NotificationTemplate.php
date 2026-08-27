<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasUuids;

    protected $fillable = ['event', 'channel', 'subject', 'body', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
