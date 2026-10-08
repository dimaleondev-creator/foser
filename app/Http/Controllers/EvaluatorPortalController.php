<?php

namespace App\Http\Controllers;

use App\Services\ApplicationEvaluationService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class EvaluatorPortalController extends Controller
{
    public function index(): View
    {
        $evaluations = DB::table('evaluations')->join('applications', 'applications.id', '=', 'evaluations.application_id')->join('calls', 'calls.id', '=', 'applications.call_id')->where('evaluations.evaluator_id', Auth::id())->select('evaluations.*', 'applications.reference', 'applications.project_title', 'calls.title as call_title')->latest('evaluations.updated_at')->get();
        return view('evaluator.index', [
            'evaluations' => $evaluations,
            'pendingCount' => $evaluations->where('status', 'assigned')->count(),
            'completedCount' => $evaluations->whereIn('status', ['submitted', 'validated'])->count(),
            'lateCount' => $evaluations->filter(fn ($evaluation): bool => $evaluation->status === 'assigned' && $evaluation->due_at && now()->greaterThan($evaluation->due_at))->count(),
        ]);
    }

    public function show(string $application, ApplicationEvaluationService $service): View
    {
        $evaluation = DB::table('evaluations')->where('application_id', $application)->where('evaluator_id', Auth::id())->firstOrFail();
        $service->startEvaluation($evaluation->id, (int) Auth::id());
        $evaluation = DB::table('evaluations')->where('id', $evaluation->id)->firstOrFail();
        $record = DB::table('applications')->where('id', $application)->select('id', 'reference', 'project_title', 'summary', 'description', 'domain', 'objectives', 'methodology', 'calendar', 'budget', 'program_id')->firstOrFail();
        $criteria = DB::table('evaluation_criteria')->where('program_id', $record->program_id)->orderBy('name')->get();
        $scores = DB::table('evaluation_scores')->where('evaluation_id', $evaluation->id)->pluck('score', 'criterion_id');
        $criterionComments = DB::table('evaluation_scores')->where('evaluation_id', $evaluation->id)->pluck('comment', 'criterion_id');
        $documents = DB::table('application_documents')->join('documents', 'documents.id', '=', 'application_documents.document_id')->where('application_documents.application_id', $application)->select('documents.id', 'documents.title', 'documents.document_type', 'documents.mime_type', 'documents.size')->orderBy('documents.created_at')->get();

        return view('evaluator.show', compact('evaluation', 'record', 'criteria', 'scores', 'criterionComments', 'documents'));
    }

    public function downloadDocument(string $application, string $document): StreamedResponse
    {
        $record = DB::table('documents')
            ->join('application_documents', 'application_documents.document_id', '=', 'documents.id')
            ->join('evaluations', 'evaluations.application_id', '=', 'application_documents.application_id')
            ->where('evaluations.application_id', $application)
            ->where('evaluations.evaluator_id', Auth::id())
            ->whereIn('evaluations.status', ['assigned', 'in_progress', 'submitted', 'validated'])
            ->where('documents.id', $document)
            ->select('documents.*')
            ->firstOrFail();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($record->disk ?: 'local');
        abort_unless($disk->exists($record->path), 404);

        return $disk->download($record->path, basename($record->title).'.'.(pathinfo($record->path, PATHINFO_EXTENSION) ?: 'pdf'));
    }

    public function submit(Request $request, string $application, ApplicationEvaluationService $service): RedirectResponse
    {
        $evaluation = DB::table('evaluations')->where('application_id', $application)->where('evaluator_id', Auth::id())->firstOrFail();
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['required', 'numeric', 'min:0'], 'comments' => ['nullable', 'array'], 'comments.*' => ['nullable', 'string', 'max:2000'], 'comment' => ['nullable', 'string', 'max:5000']]);
        $service->submitEvaluation($evaluation->id, (int) Auth::id(), $data['scores'], $data['comment'] ?? null, $data['comments'] ?? []);
        return redirect()->route('evaluator.dashboard')->with('status', 'Évaluation soumise.');
    }

    public function conflict(Request $request, string $application, ApplicationEvaluationService $service): RedirectResponse
    {
        $evaluation = DB::table('evaluations')->where('application_id', $application)->where('evaluator_id', Auth::id())->firstOrFail();
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->declareConflict($evaluation->id, (int) Auth::id(), $data['reason']);
        return redirect()->route('evaluator.dashboard')->with('status', 'Conflit déclaré.');
    }

    public function assign(Request $request, string $application, ApplicationEvaluationService $service): RedirectResponse
    {
        $data = $request->validate(['evaluator_id' => ['required', 'integer', 'exists:users,id']]);
        $service->assignEvaluator($application, (int) $data['evaluator_id']);
        return back()->with('status', 'Évaluateur attribué.');
    }

    public function reassign(Request $request, string $application, string $evaluation, ApplicationEvaluationService $service): RedirectResponse
    {
        $data = $request->validate(['evaluator_id' => ['required', 'integer', 'exists:users,id']]);
        $assignment = DB::table('evaluations')->where('id', $evaluation)->where('application_id', $application)->firstOrFail();
        $service->reassignEvaluator($assignment->id, (int) $data['evaluator_id']);
        return back()->with('status', 'Évaluation réattribuée.');
    }

    public function validateAssignment(string $evaluation, ApplicationEvaluationService $service): RedirectResponse
    {
        $service->validateEvaluation(Auth::user(), $evaluation);
        return back()->with('status', 'Évaluation validée pour la commission.');
    }

    public function removeAssignment(string $application, string $evaluation): RedirectResponse
    {
        $assignment = DB::table('evaluations')->where('id', $evaluation)->where('application_id', $application)->firstOrFail();
        abort_unless(in_array($assignment->status, ['assigned', 'in_progress', 'conflict'], true), 422);
        DB::table('evaluations')->where('id', $evaluation)->delete();
        app(\App\Services\AuditLogger::class)->record('evaluation.unassigned', 'evaluations', $evaluation, (array) $assignment, []);
        return back()->with('status', 'Attribution retirée.');
    }

    public function publishResult(Request $request, string $application, ApplicationEvaluationService $service): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:accepted,rejected,waitlisted'], 'score' => ['nullable', 'numeric', 'min:0'], 'reason' => ['nullable', 'string', 'max:5000']]);
        $service->publishResult($application, $data['decision'], isset($data['score']) ? (float) $data['score'] : null, $data['reason'] ?? null);
        return back()->with('status', 'Résultat publié.');
    }
}