<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class University extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'universities';

    protected $fillable = [
        'name',
        'short_name',
        'code',
        'country',
        'city',
        'website',
        'type',
        'status',
    ];

    public function responsibles()
    {
        return $this->hasMany(User::class, 'university_id')->where('account_type', 'universite');
    }
}
