<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ClaimResource;
use App\Models\Claim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ClaimController extends ApiController
{
    protected string $model = Claim::class;
    protected string $resource = ClaimResource::class;
    protected array $searchable = ['reference', 'subject', 'description', 'status'];
    protected array $filterable = ['claimant_id', 'application_id', 'status'];
    protected array $sortable = ['reference', 'resolved_at', 'created_at'];

    protected function scopeQuery(Builder $query, Request $request): void
    {
        $user = $request->user();

        if ($this->isInstitutionalStaff($user)) {
            return;
        }

        if ($user->hasRole('etudiant')) {
            $query->where('claimant_id', $user->id);
            return;
        }

        if ($user->hasRole('universite')) {
            $query->whereIn('claimant_id', function ($profileQuery) use ($user): void {
                $profileQuery->select('user_id')
                    ->from('student_profiles')
                    ->whereIn('university_id', $this->universityIdsFor($user));
            });
            return;
        }

        abort(403);
    }
}
