<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\CommissionApplication;
use App\Models\CommissionMember;
use App\Models\Download;
use App\Services\CommissionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionPortalController extends Controller
{
    public function index(): View
    {
        abort_unless(request()->user()->status === 'active', 403);
        Gate::authorize('viewAny', Commission::class);
        $user = request()->user();
        $commissions = Commission::query()->with(['call', 'program'])
            ->when(! $user->can('commissions.manage'), fn ($query) => $query->whereHas('members', fn ($members) => $members->where('user_id', $user->id)))
            ->orderByDesc('scheduled_at')->paginate(20);

        return view('commissions.index', compact('commissions'));
    }

    public function show(Commission $commission, CommissionWorkflowService $workflow): View
    {
        abort_unless(request()->user()->status === 'active', 403);
        Gate::authorize('view', $commission);
        $user = request()->user();
        $canManage = $user->can('commissions.manage');
        $canProposeDecision = Gate::allows('decide', $commission);
        $canManageMinutes = Gate::allows('manageMinutes', $commission);
        $quorum = $workflow->quorum($commission);
        $members = $commission->members()->with('user')->orderByRaw("CASE role WHEN 'president' THEN 0 WHEN 'secretary' THEN 1 WHEN 'rapporteur' THEN 2 ELSE 3 END")->get();
        $items = $commission->applications()->with(['application.applicant', 'application.program', 'decision'])->get();

        foreach ($items as $item) {
            $application = $item->application;
            $profile = DB::table('student_profiles')->where('user_id', $application->applicant_id)->first();
            $item->university_name = $profile?->university_id ? DB::table('universities')->where('id', $profile->university_id)->value('name') : null;
            $item->evaluations = DB::table('evaluations')->join('users', 'users.id', '=', 'evaluations.evaluator_id')
                ->where('evaluations.application_id', $application->id)
                ->select('evaluations.id', 'evaluations.status', 'evaluations.comment', 'users.name as evaluator_name')
                ->orderBy('users.name')->get();
            foreach ($item->evaluations as $evaluation) {
                $evaluation->scores = DB::table('evaluation_scores')->join('evaluation_criteria', 'evaluation_criteria.id', '=', 'evaluation_scores.criterion_id')
                    ->where('evaluation_scores.evaluation_id', $evaluation->id)
                    ->select('evaluation_criteria.name', 'evaluation_criteria.maximum_score', 'evaluation_scores.score', 'evaluation_scores.comment')
                    ->orderBy('evaluation_criteria.name')->get();
            }
            $item->documents = DB::table('application_documents')->join('documents', 'documents.id', '=', 'application_documents.document_id')
                ->where('application_documents.application_id', $application->id)
                ->select('documents.id', 'documents.title', 'documents.document_type', 'application_documents.status')
                ->orderBy('documents.created_at')->get();
            $item->required_documents = DB::table('required_documents')->where('program_id', $application->program_id)->where('is_required', true)->orderBy('sort_order')->get(['document_type', 'label']);
            $providedTypes = $item->documents->pluck('document_type')->all();
            $item->missing_documents = $item->required_documents->filter(fn (object $required): bool => ! in_array($required->document_type, $providedTypes, true))->values();
            $item->my_vote = $item->votes()->whereHas('member', fn ($query) => $query->where('user_id', $user->id))->value('vote');
            $item->can_vote = $user->status === 'active'
                && $commission->status === 'in_progress'
                && $commission->members()->where('user_id', $user->id)->where('attendance_status', 'present')->exists()
                && ! $item->my_vote;
            $item->vote_tally = $commission->status === 'completed' ? $workflow->voteTally($item) : [];
        }

        $minutes = $commission->minutes()->with(['document', 'author', 'validator'])->first();
        if ($minutes?->status !== 'validated' && ! $canManage && ! $canManageMinutes) {
            $minutes = null;
        }
        $history = Gate::allows('viewHistory', $commission)
            ? DB::table('audit_logs')->where('auditable_type', Commission::class)->where('auditable_id', $commission->id)->latest('created_at')->limit(100)->get()
            : collect();

        return view('commissions.show', compact('commission', 'quorum', 'members', 'items', 'minutes', 'history', 'canManage', 'canProposeDecision', 'canManageMinutes'));
    }

    public function vote(Request $request, Commission $commission, CommissionWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'commission_application_id' => ['required', 'uuid'],
            'vote' => ['required', 'in:favorable,defavorable,abstention'],
            'comment' => ['nullable', 'string', 'max:3000'],
        ]);
        $workflow->castVote($request->user(), $commission, $data['commission_application_id'], $data['vote'], $data['comment'] ?? null);

        return back()->with('status', 'Votre vote a été enregistré. Il ne pourra plus être modifié.');
    }

    public function proposeDecision(Request $request, Commission $commission, CommissionWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'commission_application_id' => ['required', 'uuid'],
            'decision' => ['required', 'in:accepted,rejected,adjourned,complement_requested'],
            'justification' => ['required', 'string', 'max:10000'],
        ]);
        $workflow->proposeDecision($request->user(), $commission, $data['commission_application_id'], $data['decision'], $data['justification']);

        return back()->with('status', 'Délibération proposée pour validation.');
    }

    public function saveMinutes(Request $request, Commission $commission, CommissionWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'summary' => ['required', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:30000'],
            'pdf' => ['nullable', 'file', 'max:20480', 'mimes:pdf', 'mimetypes:application/pdf'],
        ]);
        $path = $request->file('pdf')?->store('commission-minutes', 'local');

        try {
            $workflow->saveMinutes($request->user(), $commission, $data['summary'], $data['content'], $path);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('status', 'Procès-verbal enregistré pour validation.');
    }

    public function downloadApplicationDocument(Commission $commission, string $application, string $document, CommissionWorkflowService $workflow): StreamedResponse
    {
        abort_unless(request()->user()->status === 'active', 403);
        $workflow->assertCanViewApplication(request()->user(), $commission, $application);
        $record = DB::table('documents')->join('application_documents', 'application_documents.document_id', '=', 'documents.id')
            ->where('application_documents.application_id', $application)
            ->where('documents.id', $document)->whereNull('documents.deleted_at')
            ->select('documents.*')->firstOrFail();
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($record->disk ?: 'local');
        abort_unless($disk->exists($record->path), 404);

        Download::query()->create(['document_id' => $record->id, 'user_id' => request()->user()->id, 'ip_address' => request()->ip()]);

        return $disk->download($record->path, basename($record->title), ['X-Content-Type-Options' => 'nosniff']);
    }

    public function downloadMinutes(Commission $commission): StreamedResponse
    {
        abort_unless(request()->user()->status === 'active', 403);
        Gate::authorize('view', $commission);
        $minutes = $commission->minutes()->with('document')->firstOrFail();
        abort_unless($minutes->status === 'validated' || request()->user()->can('commissions.manage'), 404);
        abort_unless($minutes->document, 404);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($minutes->document->disk ?: 'local');
        abort_unless($disk->exists($minutes->document->path), 404);

        Download::query()->create(['document_id' => $minutes->document->id, 'user_id' => request()->user()->id, 'ip_address' => request()->ip()]);

        return $disk->download($minutes->document->path, basename($minutes->document->title).'.pdf', ['X-Content-Type-Options' => 'nosniff']);
    }
}