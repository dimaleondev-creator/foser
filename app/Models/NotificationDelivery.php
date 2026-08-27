<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'event', 'channel', 'idempotency_key', 'status', 'attempts', 'payload', 'last_error', 'sent_at'];
    protected function casts(): array { return ['payload' => 'encrypted:array', 'sent_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
}
