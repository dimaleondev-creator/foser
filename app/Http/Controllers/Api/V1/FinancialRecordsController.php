<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApplicationAwardApiResource;
use App\Http\Resources\ApplicationResultApiResource;
use App\Http\Resources\DisbursementApiResource;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialRecordsController
{
    public function results(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = DB::table('application_results')
            ->join('applications', 'applications.id', '=', 'application_results.application_id')
            ->whereNotNull('application_results.published_at')
            ->select('application_results.*', 'applications.reference as application_reference');

        if ($this->isStaff($user)) {
            abort_unless($user->can('applications.view'), 403);
        } elseif ($user->hasAnyRole(['etudiant', 'chercheur', 'researcher'])) {
            $query->where('applications.applicant_id', $user->id);
        } elseif ($user->hasRole('universite')) {
            $query->whereIn('applications.applicant_id', function (Builder $builder) use ($user): void {
                $builder->select('user_id')->from('student_profiles')->whereIn('university_id', $this->universityIds($user));
            });
        } elseif ($user->hasRole('evaluateur')) {
            $query->whereIn('applications.id', DB::table('evaluations')->select('application_id')->where('evaluator_id', $user->id));
        } else {
            abort(403);
        }

        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $paginator = $query->latest('application_results.published_at')->paginate($data['per_page'] ?? 20)->withQueryString();

        return response()->json(['data' => ApplicationResultApiResource::collection($paginator->items()), 'meta' => $this->pagination($paginator)]);
    }

    public function awards(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = DB::table('application_awards')->join('programs', 'programs.id', '=', 'application_awards.program_id')
            ->select('application_awards.*', 'programs.currency');

        if ($user->can('finance.view')) {
            $query->where('application_awards.status', 'active');
        } elseif ($user->hasAnyRole(['etudiant', 'chercheur', 'researcher'])) {
            $query->where('application_awards.beneficiary_id', $user->id)->where('application_awards.status', 'active');
        } else {
            abort(403);
        }

        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $paginator = $query->latest('application_awards.award_date')->paginate($data['per_page'] ?? 20)->withQueryString();

        return response()->json(['data' => ApplicationAwardApiResource::collection($paginator->items()), 'meta' => $this->pagination($paginator)]);
    }

    public function disbursements(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('finance.view'), 403);
        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'application_id' => ['nullable', 'uuid', 'exists:applications,id'],
        ]);
        $query = DB::table('disbursements')->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id')
            ->select('disbursements.*')
            ->when($data['status'] ?? null, fn (Builder $query, string $status) => $query->where('disbursements.status', $status))
            ->when($data['application_id'] ?? null, fn (Builder $query, string $id) => $query->where('financial_commitments.application_id', $id));
        $paginator = $query->latest('disbursements.created_at')->paginate($data['per_page'] ?? 20)->withQueryString();

        return response()->json(['data' => DisbursementApiResource::collection($paginator->items()), 'meta' => $this->pagination($paginator)]);
    }

    private function isStaff(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'directeur_general', 'gestionnaire', 'agent_dossier', 'agent_finance', 'agent_recherche']);
    }

    private function universityIds(User $user): Builder
    {
        $query = DB::table('university_users')->select('university_id')->where('user_id', $user->id);
        if ($user->university_id) {
            $query->orWhere('university_id', $user->university_id);
        }
        return $query;
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}