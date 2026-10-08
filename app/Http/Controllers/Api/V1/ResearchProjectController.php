<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ResearchProjectResource;
use App\Models\ResearchProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResearchProjectController extends ApiController
{
    protected string $model = ResearchProject::class;
    protected string $resource = ResearchProjectResource::class;
    protected array $searchable = ['title', 'reference', 'abstract', 'status'];
    protected array $filterable = ['research_program_id', 'laboratory_id', 'principal_researcher_id', 'status'];
    protected array $sortable = ['title', 'starts_at', 'ends_at', 'created_at'];

    protected function scopeQuery(Builder $query, Request $request): void
    {
        $user = $request->user();

        if ($this->isInstitutionalStaff($user)) {
            return;
        }

        if ($user->hasAnyRole(['chercheur', 'researcher'])) {
            $query->where(function (Builder $projectQuery) use ($user): void {
                $projectQuery->where('principal_researcher_id', $user->id)
                    ->orWhereIn('id', DB::table('research_project_members')
                        ->select('research_project_id')
                        ->where('user_id', $user->id));
            });
            return;
        }

        if ($user->hasRole('evaluateur')) {
            $query->whereIn('id', DB::table('research_project_evaluations')
                ->select('research_project_id')
                ->where('evaluator_id', $user->id));
            return;
        }

        if ($user->hasRole('universite')) {
            $query->whereIn('laboratory_id', DB::table('laboratories')
                ->select('id')
                ->whereIn('university_id', $this->universityIdsFor($user)));
            return;
        }

        abort(403);
    }
}
