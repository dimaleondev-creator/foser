<?php

namespace App\Models;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Call extends Model
{
    use HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'calls';
    protected $fillable = ['program_id', 'category_id', 'title', 'reference', 'description', 'objectives', 'domains', 'beneficiaries', 'opens_at', 'closes_at', 'places', 'amount', 'currency', 'available_budget', 'maximum_project_amount', 'conditions', 'required_documents', 'eligibility_roles', 'published_at', 'contact', 'application_url', 'regulation_path', 'terms_path', 'status', 'results_published_at'];
    protected function casts(): array
    {
        return ['opens_at' => 'date', 'closes_at' => 'date', 'published_at' => 'datetime', 'results_published_at' => 'datetime', 'eligibility_roles' => 'array', 'amount' => 'decimal:2', 'available_budget' => 'decimal:2', 'maximum_project_amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::created(fn (self $call) => app(AuditLogger::class)->record('call.created', self::class, $call->id, [], $call->getAttributes()));
        static::updated(fn (self $call) => app(AuditLogger::class)->record('call.updated', self::class, $call->id, $call->getOriginal(), $call->getChanges()));
        static::deleted(fn (self $call) => app(AuditLogger::class)->record('call.deleted', self::class, $call->id, $call->getOriginal()));
    }
}