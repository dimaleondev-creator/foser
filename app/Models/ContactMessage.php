<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasUuids;

    protected $table = 'contact_messages';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'ip_address',
        'user_agent',
    ];

    public function replies()
    {
        return $this->hasMany(ContactMessageReply::class)->orderBy('created_at');
    }

    protected $casts = ['read_at' => 'datetime', 'closed_at' => 'datetime'];
}
