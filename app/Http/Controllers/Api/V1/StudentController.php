<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Http\Resources\StudentResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends ApiController
{
    protected string $model = User::class;
    protected string $resource = StudentResource::class;
    protected array $searchable = [];
    protected array $filterable = ['status'];
    protected array $sortable = ['name', 'created_at', 'updated_at'];

    protected function scopeQuery(Builder $query, Request $request): void
    {
        $query->where('users.account_type', 'etudiant');
        $user = $request->user();

        if ($this->isInstitutionalStaff($user)) {
            return;
        }

        if ($user->hasRole('universite')) {
            $query->whereIn('users.id', function ($profileQuery) use ($user): void {
                $profileQuery->select('user_id')
                    ->from('student_profiles')
                    ->whereIn('university_id', $this->universityIdsFor($user));
            });
            return;
        }

        if ($user->can('users.view')) {
            return;
        }

        abort_unless($user->hasAnyRole(['etudiant']), 403);
        $query->where('users.id', $user->id);
    }

    protected function applyQuery(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery->where('users.name', 'like', $term)
                    ->orWhere('users.email', 'like', $term)
                    ->orWhereExists(function ($profileQuery) use ($term): void {
                        $profileQuery->selectRaw('1')
                            ->from('student_profiles')
                            ->whereColumn('student_profiles.user_id', 'users.id')
                            ->where('student_profiles.inee', 'like', $term);
                    });
            });
        }

        if (isset($filters['status'])) {
            $query->where('users.status', $filters['status']);
        }

        $sort = in_array($filters['sort'] ?? '', $this->sortable, true) ? $filters['sort'] : 'created_at';
        $query->orderBy('users.'.$sort, $filters['direction'] ?? 'desc');
    }
}
