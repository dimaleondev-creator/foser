<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsCategory extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'news_categories';

    protected $fillable = ['name', 'slug'];
}
