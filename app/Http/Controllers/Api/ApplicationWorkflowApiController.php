<?php

namespace App\Http\Controllers\Api;

use App\Services\ApplicationWorkflowService;
use App\Services\StudentApplicationWorkflow;
use App\Enums\StudentApplicationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationWorkflowApiController
{
    public function submit(Request $request, string $application, ApplicationWorkflowService $workflow): JsonResponse
    {
        $request->validate(['confirmation' => ['required', 'accepted']]);
        $workflow->submitApplication($request->user(), $application);
        return response()->json(['data' => ['application_id' => $application, 'status' => 'submitted']]);
    }

    public function verify(Request $request, string $application, ApplicationWorkflowService $workflow): JsonResponse
    {
        $workflow->verifyCompleteness($request->user(), $application);
        return response()->json(['data' => ['application_id' => $application, 'status' => 'university_review']]);
    }

    public function assign(Request $request, string $application, ApplicationWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(['evaluator_ids' => ['required', 'array', 'min:1'], 'evaluator_ids.*' => ['integer', 'exists:users,id']]);
        $workflow->assignEvaluators($request->user(), $application, $data['evaluator_ids']);
        return response()->json(['data' => ['application_id' => $application, 'status' => 'evaluation']]);
    }

    public function commission(Request $request, string $application, ApplicationWorkflowService $workflow): JsonResponse
    {
        $workflow->sendToCommission($request->user(), $application);
        return response()->json(['data' => ['application_id' => $application, 'status' => 'commission_review']]);
    }

    public function decision(Request $request, string $application, ApplicationWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:accepted,rejected,waitlisted,deferred'], 'amount' => ['nullable', 'numeric', 'min:0'], 'reason' => ['nullable', 'string', 'max:5000']]);
        $workflow->recordDecision($request->user(), $application, $data['decision'], isset($data['amount']) ? (float) $data['amount'] : null, $data['reason'] ?? null);
        return response()->json(['data' => ['application_id' => $application, 'status' => 'decision_made']]);
    }

    public function publish(Request $request, string $application, ApplicationWorkflowService $workflow): JsonResponse
    {
        $workflow->publishResult($request->user(), $application);
        return response()->json(['data' => ['application_id' => $application, 'status' => 'result_published']]);
    }

    public function award(Request $request, string $application, ApplicationWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0']]);
        $award = $workflow->createAward($request->user(), $application, (float) $data['amount']);
        return response()->json(['data' => $award], 201);
    }

    public function commit(Request $request, string $application, ApplicationWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0'], 'currency' => ['nullable', 'string', 'size:3']]);
        $workflow->sendToFinance($request->user(), $application);
        $commitment = $workflow->createFinancialCommitment($request->user(), $application, (float) $data['amount'], $data['currency'] ?? 'GNF');
        return response()->json(['data' => $commitment], 201);
    }

    public function disburse(Request $request, string $application, ApplicationWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0'], 'payment_reference' => ['required', 'string', 'max:120']]);
        $workflow->prepareDisbursement($request->user(), $application, (float) $data['amount']);
        $workflow->completeDisbursement($request->user(), $application, $data['payment_reference']);
        return response()->json(['data' => ['application_id' => $application, 'status' => 'disbursed']]);
    }
}