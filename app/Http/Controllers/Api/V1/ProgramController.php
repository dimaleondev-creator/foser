<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Program;
use App\Http\Resources\ProgramResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProgramController extends ApiController
{
    protected string $model = Program::class;
    protected string $resource = ProgramResource::class;
    protected array $searchable = ['name', 'code', 'description'];
    protected array $filterable = ['type', 'status', 'currency'];
    protected array $sortable = ['name', 'starts_at', 'ends_at', 'created_at'];

    protected function scopeQuery(Builder $query, Request $request): void
    {
        if (! $this->isInstitutionalStaff($request->user())) {
            $query->where('status', 'published');
        }
    }
}
