<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EvaluatorAssignmentResource;
use App\Models\User;
use App\Services\ApplicationEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluatorAssignmentController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $filters = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'status' => ['nullable', 'string', 'max:30']]);
        $assignments = DB::table('evaluations')->where('evaluator_id', $user->id)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()->paginate($filters['per_page'] ?? 20)->withQueryString();
        return response()->json(['data' => EvaluatorAssignmentResource::collection($assignments->items()), 'meta' => ['current_page' => $assignments->currentPage(), 'last_page' => $assignments->lastPage(), 'per_page' => $assignments->perPage(), 'total' => $assignments->total()]]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $assignment = DB::table('evaluations')->where('id', $id)->where('evaluator_id', $this->user($request)->id)->firstOrFail();
        return response()->json(['data' => new EvaluatorAssignmentResource($assignment)]);
    }

    public function start(Request $request, string $id, ApplicationEvaluationService $service): JsonResponse
    {
        $user = $this->user($request);
        $assignment = DB::table('evaluations')->where('id', $id)->where('evaluator_id', $user->id)->firstOrFail();
        $service->startEvaluation($assignment->id, $user->id);
        return response()->json(['data' => ['id' => $assignment->id, 'status' => 'in_progress']]);
    }

    public function submit(Request $request, string $id, ApplicationEvaluationService $service): JsonResponse
    {
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['required', 'numeric', 'min:0'], 'comments' => ['nullable', 'array'], 'comments.*' => ['nullable', 'string', 'max:2000'], 'comment' => ['nullable', 'string', 'max:5000']]);
        $service->submitEvaluation($id, $this->user($request)->id, $data['scores'], $data['comment'] ?? null, $data['comments'] ?? []);
        return response()->json(['data' => ['id' => $id, 'status' => 'submitted']]);
    }

    public function conflict(Request $request, string $id, ApplicationEvaluationService $service): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->declareConflict($id, $this->user($request)->id, $data['reason']);
        return response()->json(['data' => ['id' => $id, 'status' => 'conflict']]);
    }

    private function user(Request $request): User
    {
        return User::query()->findOrFail($request->user()->getAuthIdentifier());
    }
}