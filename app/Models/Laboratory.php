<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Laboratory extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['university_id', 'name', 'code', 'description', 'status'];
    public function university() { return $this->belongsTo(University::class); }
}
