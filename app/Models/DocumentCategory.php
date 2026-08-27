<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentCategory extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'document_categories';
    protected $fillable = ['name', 'slug'];
}
