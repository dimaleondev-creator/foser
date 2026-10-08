<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Call;
use App\Http\Resources\CallResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CallController extends ApiController
{
    protected string $model = Call::class;
    protected string $resource = CallResource::class;
    protected array $searchable = ['title', 'reference', 'description'];
    protected array $filterable = ['program_id', 'status', 'currency'];
    protected array $sortable = ['title', 'opens_at', 'closes_at', 'created_at'];

    protected function scopeQuery(Builder $query, Request $request): void
    {
        if (! $this->isInstitutionalStaff($request->user())) {
            $query->whereIn('status', ['published', 'open', 'scheduled', 'suspended', 'closed'])
                ->where(function (Builder $publicQuery): void {
                    $publicQuery->where(function (Builder $activeQuery): void {
                        $activeQuery->whereIn('status', ['published', 'open', 'scheduled'])
                            ->whereDate('opens_at', '<=', today())
                            ->whereDate('closes_at', '>=', today())
                            ->where(function (Builder $publicationQuery): void {
                                $publicationQuery->whereNull('published_at')->orWhere('published_at', '<=', now());
                            });
                    })->orWhereIn('status', ['suspended', 'closed']);
                });
        }
    }
}
