<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\EvaluationResource;
use App\Models\Evaluation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluationController extends ApiController
{
    protected string $model = Evaluation::class;
    protected string $resource = EvaluationResource::class;
    protected array $searchable = ['status', 'comment'];
    protected array $filterable = ['application_id', 'evaluator_id', 'status'];
    protected array $sortable = ['submitted_at', 'created_at', 'updated_at'];

    protected function scopeQuery(Builder $query, Request $request): void
    {
        $user = $request->user();

        if ($this->isInstitutionalStaff($user)) {
            return;
        }

        if ($user->hasRole('evaluateur')) {
            $query->where('evaluator_id', $user->id);
            return;
        }

        if ($user->hasRole('universite')) {
            $query->whereIn('application_id', function ($applicationQuery) use ($user): void {
                $applicationQuery->select('applications.id')
                    ->from('applications')
                    ->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')
                    ->whereIn('student_profiles.university_id', $this->universityIdsFor($user));
            });
            return;
        }

        abort(403);
    }
}
