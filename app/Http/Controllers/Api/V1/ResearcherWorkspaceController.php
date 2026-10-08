<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ResearcherProfileResource;
use App\Http\Resources\ResearchProjectApiResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResearcherWorkspaceController
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyRole(['chercheur', 'researcher']), 403);

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile' => new ResearcherProfileResource(DB::table('researcher_profiles')->where('user_id', $user->id)->first()),
        ]]);
    }

    public function projects(Request $request): JsonResponse
    {
        $user = $this->researcher($request);
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $projects = DB::table('research_projects')->where(function ($query) use ($user): void {
            $query->where('principal_researcher_id', $user->id)->orWhereIn('id', DB::table('research_project_members')->where('user_id', $user->id)->select('research_project_id'));
        })->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->where(fn ($search) => $search->where('title', 'like', "%{$term}%")->orWhere('reference', 'like', "%{$term}%")))
            ->latest()->paginate($filters['per_page'] ?? 20)->withQueryString();

        return response()->json(['data' => ResearchProjectApiResource::collection($projects->items()), 'meta' => ['current_page' => $projects->currentPage(), 'last_page' => $projects->lastPage(), 'per_page' => $projects->perPage(), 'total' => $projects->total()]]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        $user = $this->researcher($request);
        abort_unless($user->can('research.manage'), 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'abstract' => ['nullable', 'string', 'max:10000'], 'domain' => ['nullable', 'string', 'max:255'], 'laboratory_id' => ['nullable', 'uuid', 'exists:laboratories,id'], 'budget' => ['nullable', 'numeric', 'min:0'], 'currency' => ['nullable', 'string', 'size:3'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at']]);
        if (! empty($data['laboratory_id'])) {
            $profile = DB::table('researcher_profiles')->where('user_id', $user->id)->first();
            $laboratory = DB::table('laboratories')->where('id', $data['laboratory_id'])->first();
            abort_unless($laboratory && $profile?->university_id && $laboratory->university_id === $profile->university_id, 422, 'Le laboratoire doit appartenir à votre établissement.');
        }
        $id = (string) Str::uuid();
        DB::table('research_projects')->insert(['id' => $id, 'principal_researcher_id' => $user->id, 'reference' => 'FOSER-RECH-'.now()->format('Y').'-'.str_pad((string) (DB::table('research_projects')->count() + 1), 6, '0', STR_PAD_LEFT), 'status' => 'draft', 'currency' => $data['currency'] ?? 'FCFA', ...$data, 'created_at' => now(), 'updated_at' => now()]);
        app(AuditLogger::class)->record('researcher.project_created_api', 'research_projects', $id, [], ['principal_researcher_id' => $user->id]);

        return response()->json(['data' => new ResearchProjectApiResource(DB::table('research_projects')->where('id', $id)->first())], 201);
    }

    public function showProject(Request $request, string $project): JsonResponse
    {
        $record = $this->ownedProject($request, $project);
        return response()->json(['data' => new ResearchProjectApiResource($record)]);
    }

    public function updateProject(Request $request, string $project): JsonResponse
    {
        $user = $this->researcher($request);
        abort_unless($user->can('research.manage'), 403);
        $record = $this->ownedProject($request, $project);
        abort_unless(in_array($record->status, ['draft', 'complement'], true), 422);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'abstract' => ['nullable', 'string', 'max:10000'], 'domain' => ['nullable', 'string', 'max:255'], 'budget' => ['nullable', 'numeric', 'min:0'], 'currency' => ['nullable', 'string', 'size:3'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at']]);
        DB::table('research_projects')->where('id', $record->id)->update([...$data, 'updated_at' => now()]);
        return response()->json(['data' => new ResearchProjectApiResource(DB::table('research_projects')->where('id', $record->id)->first())]);
    }

    public function submitProject(Request $request, string $project): JsonResponse
    {
        $user = $this->researcher($request);
        abort_unless($user->can('research.manage'), 403);
        $record = $this->ownedProject($request, $project);
        abort_unless($record->status === 'draft', 422);
        abort_unless(filled($record->title) && filled($record->abstract) && filled($record->domain) && $record->budget !== null, 422, 'Le projet est incomplet.');
        DB::table('research_projects')->where('id', $record->id)->update(['status' => 'submitted', 'updated_at' => now()]);
        app(AuditLogger::class)->record('researcher.project_submitted_api', 'research_projects', $record->id, ['status' => 'draft'], ['status' => 'submitted']);
        return response()->json(['data' => new ResearchProjectApiResource(DB::table('research_projects')->where('id', $record->id)->first())]);
    }

    public function publications(Request $request): JsonResponse
    {
        $user = $this->researcher($request);
        $filters = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'status' => ['nullable', 'string', 'max:30'], 'search' => ['nullable', 'string', 'max:100']]);
        $paginator = DB::table('research_publications')->where('author_id', $user->id)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->where(fn ($search) => $search->where('title', 'like', "%{$term}%")->orWhere('doi', 'like', "%{$term}%")))
            ->latest()->paginate($filters['per_page'] ?? 20)->withQueryString();
        return response()->json(['data' => $paginator->items(), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()]]);
    }

    public function calls(Request $request): JsonResponse
    {
        $this->researcher($request);
        $filters = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'search' => ['nullable', 'string', 'max:100']]);
        $paginator = DB::table('calls')->where('status', 'published')->whereDate('opens_at', '<=', today())->whereDate('closes_at', '>=', today())
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->where(fn ($search) => $search->where('title', 'like', "%{$term}%")->orWhere('reference', 'like', "%{$term}%")))
            ->orderBy('closes_at')->paginate($filters['per_page'] ?? 20)->withQueryString();
        return response()->json(['data' => $paginator->items(), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()]]);
    }

    public function evaluations(Request $request, string $project): JsonResponse
    {
        $record = $this->ownedProject($request, $project);
        $evaluations = DB::table('research_project_evaluations')->where('research_project_id', $record->id)->whereIn('status', ['published', 'validated'])->select(['id', 'score', 'status', 'comment', 'created_at', 'updated_at'])->get();
        return response()->json(['data' => $evaluations]);
    }

    private function researcher(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyRole(['chercheur', 'researcher']), 403);
        return $user;
    }

    private function ownedProject(Request $request, string $project): object
    {
        $user = $this->researcher($request);
        $record = DB::table('research_projects')->where('id', $project)->where(function ($query) use ($user): void {
            $query->where('principal_researcher_id', $user->id)->orWhereIn('id', DB::table('research_project_members')->where('user_id', $user->id)->select('research_project_id'));
        })->first();
        abort_unless($record, 404);
        return $record;
    }
}
