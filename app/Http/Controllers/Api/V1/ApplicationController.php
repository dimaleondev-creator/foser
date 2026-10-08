<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationController extends ApiController
{
    protected string $model = Application::class;
    protected string $resource = ApplicationResource::class;
    protected array $searchable = ['reference', 'status', 'applicant_note'];
    protected array $filterable = ['call_id', 'program_id', 'applicant_id', 'status'];
    protected array $sortable = ['reference', 'submitted_at', 'decided_at', 'created_at'];

    protected function scopeQuery(Builder $query, Request $request): void
    {
        $user = $request->user();

        if ($this->isInstitutionalStaff($user)) {
            return;
        }

        if ($user->hasAnyRole(['etudiant', 'chercheur', 'researcher'])) {
            $query->where('applicant_id', $user->id);
            return;
        }

        if ($user->hasRole('universite')) {
            $query->whereIn('applicant_id', function ($profileQuery) use ($user): void {
                $profileQuery->select('user_id')
                    ->from('student_profiles')
                    ->whereIn('university_id', $this->universityIdsFor($user));
            });
            return;
        }

        if ($user->hasRole('evaluateur')) {
            $query->whereIn('id', DB::table('evaluations')
                ->select('application_id')
                ->where('evaluator_id', $user->id));
            return;
        }

        abort(403);
    }
}
