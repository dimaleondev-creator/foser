<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Researcher;
use App\Http\Resources\ResearcherResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ResearcherController extends ApiController
{
    protected string $model = Researcher::class;
    protected string $resource = ResearcherResource::class;
    protected array $searchable = ['researcher_number', 'orcid', 'speciality', 'research_domain', 'academic_rank'];
    protected array $filterable = ['university_id', 'academic_rank'];
    protected array $sortable = ['researcher_number', 'created_at', 'updated_at'];

    protected function scopeQuery(Builder $query, Request $request): void
    {
        $user = $request->user();

        if ($this->isInstitutionalStaff($user)) {
            return;
        }

        if ($user->hasRole('universite')) {
            $query->whereIn('university_id', $this->universityIdsFor($user));
            return;
        }

        if ($user->hasAnyRole(['chercheur', 'researcher'])) {
            $query->where('user_id', $user->id);
            return;
        }

        abort(403);
    }
}
