<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    use HasUuids;

    protected $fillable = ['document_id', 'disk', 'created_by', 'version', 'path', 'checksum', 'size'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'size' => 'integer'];
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}