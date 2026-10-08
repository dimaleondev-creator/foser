<?php

namespace App\Http\Controllers;

use App\Services\ApplicationWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplicationWorkflowController extends Controller
{
    public function verify(Request $request, string $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $workflow->verifyCompleteness($request->user(), $application);
        return back()->with('status', 'Complétude vérifiée.');
    }

    public function approveUniversity(Request $request, string $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $workflow->approveUniversityReview($request->user(), $application);
        return back()->with('status', 'Vérification universitaire validée.');
    }

    public function rejectUniversity(Request $request, string $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:5000']]);
        $workflow->rejectUniversityReview($request->user(), $application, $data['reason']);
        return back()->with('status', 'Dossier rejeté lors de la vérification universitaire.');
    }

    public function assign(Request $request, string $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['evaluator_ids' => ['required', 'array', 'min:1'], 'evaluator_ids.*' => ['integer', 'exists:users,id']]);
        $workflow->assignEvaluators($request->user(), $application, $data['evaluator_ids']);
        return back()->with('status', 'Évaluateurs affectés.');
    }

    public function commission(Request $request, string $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $workflow->sendToCommission($request->user(), $application);
        return back()->with('status', 'Dossier transmis à la commission.');
    }

    public function decision(Request $request, string $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:accepted,rejected,waitlisted,deferred'], 'amount' => ['nullable', 'numeric', 'min:0'], 'reason' => ['nullable', 'string', 'max:5000']]);
        $workflow->recordDecision($request->user(), $application, $data['decision'], isset($data['amount']) ? (float) $data['amount'] : null, $data['reason'] ?? null);
        return back()->with('status', 'Décision enregistrée.');
    }

    public function publish(Request $request, string $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $workflow->publishResult($request->user(), $application);
        return back()->with('status', 'Résultat publié.');
    }

    public function award(Request $request, string $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0']]);
        $workflow->createAward($request->user(), $application, (float) $data['amount']);
        return back()->with('status', 'Attribution créée.');
    }

    public function commit(Request $request, string $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0'], 'currency' => ['nullable', 'string', 'size:3']]);
        $workflow->createFinancialCommitment($request->user(), $application, (float) $data['amount'], $data['currency'] ?? 'FCFA');
        return back()->with('status', 'Engagement financier créé.');
    }

    public function disburse(Request $request, string $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0'], 'payment_reference' => ['required', 'string', 'max:120']]);
        $workflow->prepareDisbursement($request->user(), $application, (float) $data['amount']);
        $workflow->completeDisbursement($request->user(), $application, $data['payment_reference']);
        return back()->with('status', 'Décaissement enregistré.');
    }
}