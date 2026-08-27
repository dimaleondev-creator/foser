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
        $assignments = DB::table('evaluations')->where('evaluator_id', $user->id)->latest()->paginate(20)->withQueryString();
        return response()->json(['data' => EvaluatorAssignmentResource::collection($assignments->items()), 'meta' => ['current_page' => $assignments->currentPage(), 'last_page' => $assignments->lastPage(), 'total' => $assignments->total()]]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $assignment = DB::table('evaluations')->where('id', $id)->where('evaluator_id', $this->user($request)->id)->firstOrFail();
        return response()->json(['data' => new EvaluatorAssignmentResource($assignment)]);
    }

    public function submit(Request $request, string $id, ApplicationEvaluationService $service): JsonResponse
    {
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['required', 'numeric', 'min:0'], 'comment' => ['nullable', 'string', 'max:5000']]);
        $service->submitEvaluation($id, $this->user($request)->id, $data['scores'], $data['comment'] ?? null);
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