<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramFaq extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'program_faqs';

    protected $fillable = ['program_id', 'category', 'question', 'answer', 'status', 'sort_order'];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}