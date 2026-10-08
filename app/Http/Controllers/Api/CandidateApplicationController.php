<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CandidateApplicationResource;
use App\Models\User;
use App\Services\StudentApplicationWorkflow;
use App\Enums\StudentApplicationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CandidateApplicationController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $filters = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'status' => ['nullable', 'string', 'max:40'], 'search' => ['nullable', 'string', 'max:100']]);
        $applications = DB::table('applications')->where('applicant_id', $user->id)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->where(fn ($search) => $search->where('reference', 'like', "%{$term}%")->orWhere('project_title', 'like', "%{$term}%")))
            ->latest()->paginate($filters['per_page'] ?? 20)->withQueryString();
        return response()->json(['data' => CandidateApplicationResource::collection($applications->items()), 'meta' => ['current_page' => $applications->currentPage(), 'last_page' => $applications->lastPage(), 'per_page' => $applications->perPage(), 'total' => $applications->total()]]);
    }

    public function store(Request $request, StudentApplicationWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['call_id' => ['required', 'uuid']]);
        $application = $workflow->createDraft($this->user($request), $data['call_id']);
        return response()->json(['data' => new CandidateApplicationResource($application)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $application = DB::table('applications')->where('id', $id)->where('applicant_id', $this->user($request)->id)->firstOrFail();
        return response()->json(['data' => new CandidateApplicationResource($application)]);
    }

    public function update(Request $request, string $id, StudentApplicationWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['project_title' => ['required', 'string', 'max:255'], 'summary' => ['required', 'string', 'max:2000'], 'description' => ['required', 'string', 'max:10000'], 'domain' => ['required', 'string', 'max:255'], 'objectives' => ['required', 'string', 'max:5000'], 'methodology' => ['required', 'string', 'max:10000'], 'calendar' => ['required', 'string', 'max:5000'], 'budget' => ['required', 'numeric', 'min:0'], 'team' => ['nullable', 'string', 'max:5000'], 'applicant_note' => ['nullable', 'string', 'max:5000']]);
        $application = $workflow->updateDraft($this->user($request), $id, $data);
        return response()->json(['data' => new CandidateApplicationResource($application)]);
    }

    public function submit(Request $request, string $id, StudentApplicationWorkflow $workflow): JsonResponse
    {
        $request->validate(['confirmation' => ['required', 'accepted']]);
        $user = $this->user($request);
        $workflow->transition($user, $id, StudentApplicationStatus::SOUMIS);
        $application = DB::table('applications')->where('id', $id)->where('applicant_id', $user->id)->firstOrFail();
        return response()->json(['data' => new CandidateApplicationResource($application)]);
    }

    private function user(Request $request): User
    {
        return User::query()->findOrFail($request->user()->getAuthIdentifier());
    }
}