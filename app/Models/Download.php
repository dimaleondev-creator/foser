<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $fillable = ['document_id', 'user_id', 'ip_address', 'downloaded_at'];
    protected $casts = ['downloaded_at' => 'datetime'];
}
