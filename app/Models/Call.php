<?php

namespace App\Models;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Call extends Model
{
    use HasUuids, SoftDeletes;

    public const PUBLIC_STATUSES = ['published', 'open', 'scheduled', 'suspended', 'closed'];
    public const ACTIVE_STATUSES = ['published', 'open', 'scheduled'];

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'calls';
    protected $fillable = ['program_id', 'category_id', 'title', 'reference', 'description', 'objectives', 'domains', 'beneficiaries', 'opens_at', 'closes_at', 'places', 'amount', 'currency', 'available_budget', 'maximum_project_amount', 'conditions', 'required_documents', 'eligibility_roles', 'published_at', 'contact', 'application_url', 'regulation_path', 'terms_path', 'status', 'results_published_at'];
    protected function casts(): array
    {
        return ['opens_at' => 'date', 'closes_at' => 'date', 'published_at' => 'datetime', 'results_published_at' => 'datetime', 'eligibility_roles' => 'array', 'amount' => 'decimal:2', 'available_budget' => 'decimal:2', 'maximum_project_amount' => 'decimal:2'];
    }

    public static function publicStatusScope(): array
    {
        return self::PUBLIC_STATUSES;
    }

    public static function publicQuery(): Builder
    {
        return static::query()->whereIn('status', self::PUBLIC_STATUSES)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $activeQuery): void {
                    $activeQuery->whereIn('status', self::ACTIVE_STATUSES)
                        ->whereDate('opens_at', '<=', today())
                        ->whereDate('closes_at', '>=', today())
                        ->where(function (Builder $publishedQuery): void {
                            $publishedQuery->whereNull('published_at')->orWhere('published_at', '<=', now());
                        });
                })->orWhereIn('status', ['suspended', 'closed']);
            });
    }

    public function isOpenForApplications(): bool
    {
        if (! in_array($this->status, self::ACTIVE_STATUSES, true)) {
            return false;
        }

        $today = now()->startOfDay();

        return Carbon::parse($this->opens_at)->startOfDay()->lte($today)
            && Carbon::parse($this->closes_at)->startOfDay()->gte($today)
            && (! $this->published_at || $this->published_at->lte(now()));
    }

    public function isPubliclyVisible(): bool
    {
        if (! in_array($this->status, self::PUBLIC_STATUSES, true)) {
            return false;
        }

        if (in_array($this->status, ['published', 'open', 'scheduled'], true)) {
            return $this->isOpenForApplications();
        }

        return true;
    }

    protected static function booted(): void
    {
        static::created(fn (self $call) => app(AuditLogger::class)->record('call.created', self::class, $call->id, [], $call->getAttributes()));
        static::updated(fn (self $call) => app(AuditLogger::class)->record('call.updated', self::class, $call->id, $call->getOriginal(), $call->getChanges()));
        static::deleted(fn (self $call) => app(AuditLogger::class)->record('call.deleted', self::class, $call->id, $call->getOriginal()));
    }
}