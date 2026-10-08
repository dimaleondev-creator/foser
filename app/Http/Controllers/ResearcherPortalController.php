<?php

namespace App\Http\Controllers;

use App\Mail\ResearcherAccountApprovedMail;
use App\Mail\ResearcherRegistrationReceivedMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ResearcherPortalController extends Controller
{
    public function register(): View
    {
        return view('researcher.auth', ['mode' => 'register']);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', 'min:8'],
            'phone' => ['required', 'string', 'max:40'], 'country' => ['required', 'string', 'max:100'], 'region' => ['nullable', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:100'],
            'research_domain' => ['required', 'string', 'max:255'], 'speciality' => ['required', 'string', 'max:255'], 'academic_rank' => ['nullable', 'string', 'max:255'],
            'university_id' => ['prohibited'], 'laboratory_id' => ['prohibited'], 'position' => ['nullable', 'string', 'max:150'],
            'orcid' => ['nullable', 'string', 'max:19'], 'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'], 'biography' => ['nullable', 'string', 'max:10000'], 'main_publications' => ['nullable', 'string', 'max:10000'], 'terms' => ['accepted'],
        ]);
        $user = User::create(['name' => trim($data['first_name'].' '.$data['last_name']), 'email' => $data['email'], 'password' => $data['password'], 'account_type' => 'researcher', 'status' => 'pending']);
        $reference = 'CHR-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        DB::table('researcher_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'researcher_number' => $reference, 'registration_reference' => $reference, 'status' => 'pending', 'phone' => $data['phone'], 'country' => $data['country'], 'region' => $data['region'] ?? null, 'city' => $data['city'] ?? null, 'research_domain' => $data['research_domain'], 'speciality' => $data['speciality'], 'academic_rank' => $data['academic_rank'] ?? null, 'university_id' => null, 'laboratory_id' => null, 'position' => $data['position'] ?? null, 'orcid' => $data['orcid'] ?? null, 'years_experience' => $data['years_experience'] ?? null, 'biography' => $data['biography'] ?? null, 'main_publications' => $data['main_publications'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        try { Mail::to($user->email)->send(new ResearcherRegistrationReceivedMail($user->name, $reference)); } catch (\Throwable) { }
        DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'researcher.registration.pending', 'notifiable_type' => User::class, 'notifiable_id' => User::query()->whereIn('account_type', ['admin', 'super_admin'])->value('id') ?: $user->id, 'data' => json_encode(['name' => $user->name, 'email' => $user->email, 'reference' => $reference, 'message' => 'Nouveau compte chercheur à vérifier']), 'created_at' => now(), 'updated_at' => now()]);
        return redirect()->route('researcher.pending', ['reference' => $reference]);
    }

    public function login(): View { return view('researcher.auth', ['mode' => 'login']); }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::query()->where('email', $data['email'])->whereIn('account_type', ['chercheur', 'researcher'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) return back()->withErrors(['email' => 'Les identifiants fournis sont incorrects.'])->onlyInput('email');
        $profile = DB::table('researcher_profiles')->where('user_id', $user->id)->first();
        if ($profile?->status === 'pending') return redirect()->route('researcher.pending');
        if ($profile?->status === 'rejected') return redirect()->route('researcher.rejected');
        if ($profile?->status === 'suspended' || $user->status !== 'active') return redirect()->route('researcher.suspended');
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('researcher.dashboard');
    }

    public function pending(): View { return view('researcher.pending'); }
    public function rejected(): View { return view('researcher.status', ['title' => 'Demande rejetée', 'message' => 'Votre demande de compte a été rejetée.']); }
    public function suspended(): View { return view('researcher.status', ['title' => 'Compte suspendu', 'message' => 'Votre compte chercheur est actuellement suspendu.']); }

    public static function approve(string $researcherId, User $admin): RedirectResponse
    {
        abort_unless($admin->can('researchers.review'), 403);
        $profile = DB::table('researcher_profiles')->where('id', $researcherId)->firstOrFail();
        abort_unless($profile->status === 'pending', 422);
        DB::transaction(function () use ($profile, $admin): void {
            DB::table('researcher_profiles')->where('id', $profile->id)->update(['status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('id', $profile->user_id)->update(['status' => 'active', 'updated_at' => now()]);
            app(\App\Services\AuditLogger::class)->record('researcher.account.approved', 'researcher_profiles', $profile->id, ['status' => 'pending'], ['status' => 'approved', 'approved_by' => $admin->id]);
        });
        $user = User::findOrFail($profile->user_id); try { Mail::to($user->email)->send(new ResearcherAccountApprovedMail($user->name)); } catch (\Throwable) { }
        return back()->with('status', 'Compte approuvé.');
    }

    public function reject(Request $request, string $researcherId): RedirectResponse
    {
        abort_unless($request->user()->can('researchers.review'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $profile = DB::table('researcher_profiles')->where('id', $researcherId)->where('status', 'pending')->firstOrFail();
        DB::transaction(function () use ($profile, $data): void {
            DB::table('researcher_profiles')->where('id', $profile->id)->update(['status' => 'rejected', 'rejection_reason' => $data['reason'], 'rejected_by' => Auth::id(), 'rejected_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('id', $profile->user_id)->update(['status' => 'disabled', 'updated_at' => now()]);
            app(\App\Services\AuditLogger::class)->record('researcher.account.rejected', 'researcher_profiles', $profile->id, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $data['reason']]);
        });
        return back()->with('status', 'Demande rejetée.');
    }

    public function suspend(Request $request, string $researcherId): RedirectResponse
    {
        abort_unless($request->user()->can('researchers.review'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $profile = DB::table('researcher_profiles')->where('id', $researcherId)->where('status', 'approved')->firstOrFail();
        DB::transaction(function () use ($profile, $data): void {
            DB::table('researcher_profiles')->where('id', $profile->id)->update(['status' => 'suspended', 'rejection_reason' => $data['reason'], 'suspended_by' => Auth::id(), 'suspended_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('id', $profile->user_id)->update(['status' => 'suspended', 'updated_at' => now()]);
            app(\App\Services\AuditLogger::class)->record('researcher.account.suspended', 'researcher_profiles', $profile->id, ['status' => 'approved'], ['status' => 'suspended', 'reason' => $data['reason']]);
        });
        return back()->with('status', 'Compte suspendu.');
    }

    public function reactivate(string $researcherId): RedirectResponse
    {
        Gate::authorize('researchers.review');
        $profile = DB::table('researcher_profiles')->where('id', $researcherId)->whereIn('status', ['suspended', 'rejected'])->firstOrFail();
        DB::transaction(function () use ($profile): void {
            DB::table('researcher_profiles')->where('id', $profile->id)->update(['status' => 'approved', 'rejection_reason' => null, 'approved_by' => Auth::id(), 'approved_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('id', $profile->user_id)->update(['status' => 'active', 'updated_at' => now()]);
            app(\App\Services\AuditLogger::class)->record('researcher.account.reactivated', 'researcher_profiles', $profile->id, ['status' => $profile->status], ['status' => 'approved']);
        });
        return back()->with('status', 'Compte réactivé.');
    }
    public function dashboard(): View
    {
        $userId = Auth::id();
        $visibleProjectIds = DB::table('research_projects')
            ->where(function ($query) use ($userId): void {
                $query->where('principal_researcher_id', $userId)
                    ->orWhereIn('id', DB::table('research_project_members')->where('user_id', $userId)->select('research_project_id'));
            })
            ->whereNull('deleted_at')
            ->select('id');
        $projectIds = clone $visibleProjectIds;
        $projects = DB::table('research_projects')
            ->leftJoin('research_programs', 'research_programs.id', '=', 'research_projects.research_program_id')
            ->leftJoin('programs', 'programs.id', '=', 'research_programs.program_id')
            ->leftJoin('laboratories', 'laboratories.id', '=', 'research_projects.laboratory_id')
            ->leftJoin('universities', 'universities.id', '=', 'laboratories.university_id')
            ->leftJoin('users as principal_researcher', 'principal_researcher.id', '=', 'research_projects.principal_researcher_id')
            ->whereIn('research_projects.id', $projectIds)
            ->select('research_projects.*', 'programs.name as program_name', 'research_programs.research_area', 'laboratories.name as laboratory_name', 'universities.name as university_name', 'principal_researcher.name as principal_researcher_name')
            ->latest('research_projects.updated_at')->limit(8)->get();
        $commitments = DB::table('financial_commitments')->whereIn('research_project_id', clone $visibleProjectIds);
        $disbursements = DB::table('research_project_disbursements')
            ->join('disbursements', 'disbursements.id', '=', 'research_project_disbursements.disbursement_id')
            ->whereIn('research_project_disbursements.research_project_id', clone $visibleProjectIds);

        $profile = DB::table('researcher_profiles')->where('user_id', $userId)->first();
        $profileFields = ['phone', 'research_domain', 'speciality', 'academic_rank', 'university_id', 'laboratory_id', 'orcid', 'biography', 'position', 'keywords', 'department'];
        $profileCompletion = (int) round((collect($profileFields)->filter(fn (string $field): bool => filled($profile?->{$field} ?? null))->count() / count($profileFields)) * 100);
        $projectStats = DB::table('research_projects')->whereIn('id', clone $visibleProjectIds)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts")
            ->selectRaw("SUM(CASE WHEN status IN ('submitted', 'verification', 'under_review', 'evaluation', 'accepted', 'rejected', 'funded') THEN 1 ELSE 0 END) as submitted")
            ->selectRaw("SUM(CASE WHEN status IN ('under_review', 'evaluation', 'verification') THEN 1 ELSE 0 END) as under_review")
            ->selectRaw("SUM(CASE WHEN status IN ('funded', 'active', 'in_progress', 'ongoing') THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN status = 'funded' THEN 1 ELSE 0 END) as funded")
            ->selectRaw("SUM(CASE WHEN status IN ('completed', 'closed', 'archive') THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status IN ('draft', 'complement') THEN 1 ELSE 0 END) as pending_actions")
            ->selectRaw('COALESCE(SUM(budget), 0) as requested_amount')
            ->first();
        $workflowStages = ['Projet créé', 'Candidature soumise', 'Vérification', 'Évaluation scientifique', 'Décision', 'Financement', 'Projet en cours', 'Projet terminé'];
        $projectStatusDetails = [
            'draft' => ['Brouillon', 0],
            'submitted' => ['Soumis', 1],
            'verification' => ['En vérification', 2],
            'complement' => ['Complément demandé', 2],
            'under_review' => ['En évaluation', 3],
            'evaluation' => ['En évaluation', 3],
            'accepted' => ['Accepté', 4],
            'rejected' => ['Rejeté', 4],
            'funded' => ['Financé', 5],
            'active' => ['En cours', 6],
            'in_progress' => ['En cours', 6],
            'ongoing' => ['En cours', 6],
            'completed' => ['Terminé', 7],
            'closed' => ['Terminé', 7],
            'archive' => ['Terminé', 7],
        ];
        $projects->transform(function (object $project) use ($projectStatusDetails): object {
            [$project->status_label, $project->workflow_step] = $projectStatusDetails[$project->status] ?? [ucfirst(str_replace('_', ' ', $project->status)), 0];
            return $project;
        });
        $statusDistribution = DB::table('research_projects')->whereIn('id', clone $visibleProjectIds)
            ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->orderByDesc('total')->limit(6)->get();
        $statusDistribution->transform(function (object $status) use ($projectStatusDetails): object {
            [$status->status_label] = $projectStatusDetails[$status->status] ?? [ucfirst(str_replace('_', ' ', $status->status)), 0];
            return $status;
        });
        $openCalls = DB::table('calls')
            ->join('programs', 'programs.id', '=', 'calls.program_id')
            ->where('calls.status', 'published')
            ->whereDate('calls.opens_at', '<=', today())
            ->whereDate('calls.closes_at', '>=', today())
            ->select('calls.*', 'programs.name as program_name')
            ->orderBy('calls.closes_at')->limit(5)->get();
        $latestDisbursements = DB::table('research_project_disbursements')
            ->join('disbursements', 'disbursements.id', '=', 'research_project_disbursements.disbursement_id')
            ->join('research_projects', 'research_projects.id', '=', 'research_project_disbursements.research_project_id')
            ->whereIn('research_project_disbursements.research_project_id', clone $visibleProjectIds)
            ->select('disbursements.*', 'research_projects.title as project_title', 'research_projects.currency')
            ->latest('disbursements.created_at')->limit(5)->get();
        $documents = DB::table('research_project_documents')
            ->join('documents', 'documents.id', '=', 'research_project_documents.document_id')
            ->join('research_projects', 'research_projects.id', '=', 'research_project_documents.research_project_id')
            ->whereIn('research_project_documents.research_project_id', clone $visibleProjectIds)
            ->select('documents.title', 'documents.document_type', 'research_project_documents.document_role', 'research_project_documents.created_at', 'research_projects.id as project_id', 'research_projects.title as project_title')
            ->latest('research_project_documents.created_at')->limit(5)->get();
        $upcomingProjectDeadlines = DB::table('research_projects')->whereIn('id', clone $visibleProjectIds)
            ->whereNotNull('ends_at')->whereDate('ends_at', '>=', today())
            ->select('id', 'title', 'ends_at')->orderBy('ends_at')->limit(5)->get();
        $evaluations = DB::table('research_project_evaluations')
            ->join('research_projects', 'research_projects.id', '=', 'research_project_evaluations.research_project_id')
            ->whereIn('research_project_evaluations.research_project_id', clone $visibleProjectIds)
            ->select('research_project_evaluations.status', 'research_project_evaluations.updated_at', 'research_projects.title as project_title')
            ->latest('research_project_evaluations.updated_at')->limit(5)->get();
        $notifications = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->latest()->limit(5)->get();
        $mainProject = $projects->first(fn (object $project): bool => in_array($project->status, ['funded', 'active', 'in_progress', 'ongoing'], true)) ?? $projects->first();
        $mainProjectDisbursed = $mainProject
            ? (float) DB::table('research_project_disbursements')
                ->join('disbursements', 'disbursements.id', '=', 'research_project_disbursements.disbursement_id')
                ->where('research_project_disbursements.research_project_id', $mainProject->id)
                ->whereIn('disbursements.status', ['disbursed', 'execute', 'paid'])
                ->sum('disbursements.amount')
            : 0;
        $mainProjectAwarded = $mainProject
            ? (float) DB::table('financial_commitments')->where('research_project_id', $mainProject->id)->whereIn('status', ['approved', 'valide', 'execute'])->sum('amount')
            : 0;
        $awardedAmount = (float) (clone $commitments)->whereIn('status', ['approved', 'valide', 'execute'])->sum('amount');
        $disbursedAmount = (float) (clone $disbursements)->whereIn('disbursements.status', ['disbursed', 'execute', 'paid'])->sum('disbursements.amount');
        $financeProgress = $awardedAmount > 0 ? min(100, (int) round($disbursedAmount / $awardedAmount * 100)) : 0;
        $events = DB::table('events')->where('status', 'published')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(4)->get();

        return view('researcher.dashboard', [
            'researcher' => Auth::user(),
            'projects' => $projects,
            'publications' => DB::table('research_publications')->where('author_id', $userId)->latest()->limit(5)->get(),
            'profile' => $profile,
            'profileCompletion' => $profileCompletion,
            'university' => $profile?->university_id ? DB::table('universities')->where('id', $profile->university_id)->first() : null,
            'laboratory' => $profile?->laboratory_id ? DB::table('laboratories')->where('id', $profile->laboratory_id)->first() : null,
            'openCalls' => $openCalls,
            'latestDisbursements' => $latestDisbursements,
            'documents' => $documents,
            'upcomingProjectDeadlines' => $upcomingProjectDeadlines,
            'evaluations' => $evaluations,
            'notifications' => $notifications,
            'mainProject' => $mainProject,
            'mainProjectDisbursed' => $mainProjectDisbursed,
            'mainProjectAwarded' => $mainProjectAwarded,
            'events' => $events,
            'statusDistribution' => $statusDistribution,
            'statusMaximum' => max(1, (int) $statusDistribution->max('total')),
            'workflowStages' => $workflowStages,
            'stats' => [
                'total_projects' => (int) $projectStats->total,
                'submitted_projects' => (int) $projectStats->submitted,
                'draft_projects' => (int) $projectStats->drafts,
                'evaluation_projects' => (int) $projectStats->under_review,
                'active_projects' => (int) $projectStats->active,
                'accepted_projects' => (int) DB::table('research_projects')->whereIn('id', clone $visibleProjectIds)->whereIn('status', ['accepted', 'funded'])->count(),
                'rejected_projects' => (int) DB::table('research_projects')->whereIn('id', clone $visibleProjectIds)->where('status', 'rejected')->count(),
                'funded_projects' => (int) $projectStats->funded,
                'completed_projects' => (int) $projectStats->completed,
                'requested_amount' => (float) $projectStats->requested_amount,
                'awarded_amount' => $awardedAmount,
                'disbursed_amount' => $disbursedAmount,
                'publications' => (int) DB::table('research_publications')->where('author_id', $userId)->count(),
                'active_conventions' => (int) DB::table('research_conventions')->whereIn('research_project_id', clone $visibleProjectIds)->whereIn('status', ['signed', 'active'])->count(),
                'unread_notifications' => (int) DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $userId)->whereNull('read_at')->count(),
                'pending_actions' => (int) $projectStats->pending_actions,
                'document_count' => (int) DB::table('research_project_documents')->whereIn('research_project_id', clone $visibleProjectIds)->count(),
                'evaluation_count' => (int) DB::table('research_project_evaluations')->whereIn('research_project_id', clone $visibleProjectIds)->count(),
                'finance_progress' => $financeProgress,
            ],
        ]);
    }

    public function workspaceSection(string $section): View
    {
        abort_unless(in_array($section, ['projects', 'applications', 'evaluations', 'finance', 'documents', 'notifications', 'calendar'], true), 404);

        $userId = Auth::id();
        $visibleProjectIds = DB::table('research_projects')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($userId): void {
                $query->where('principal_researcher_id', $userId)
                    ->orWhereIn('id', DB::table('research_project_members')->where('user_id', $userId)->select('research_project_id'));
            })
            ->select('id');

        $projects = DB::table('research_projects')
            ->whereIn('id', clone $visibleProjectIds)
            ->latest('updated_at')->paginate(15)->withQueryString();
        $projectCount = (int) DB::table('research_projects')->whereIn('id', clone $visibleProjectIds)->count();

        $data = match ($section) {
            'projects', 'applications' => ['projects' => $projects, 'projectCount' => $projectCount],
            'evaluations' => ['evaluations' => DB::table('research_project_evaluations')
                ->join('research_projects', 'research_projects.id', '=', 'research_project_evaluations.research_project_id')
                ->whereIn('research_project_evaluations.research_project_id', clone $visibleProjectIds)
                ->select('research_project_evaluations.status', 'research_project_evaluations.updated_at', 'research_projects.id as project_id', 'research_projects.title as project_title')
                ->latest('research_project_evaluations.updated_at')->paginate(15)->withQueryString()],
            'finance' => [
                'commitments' => DB::table('financial_commitments')
                    ->join('research_projects', 'research_projects.id', '=', 'financial_commitments.research_project_id')
                    ->whereIn('financial_commitments.research_project_id', clone $visibleProjectIds)
                    ->select('financial_commitments.*', 'research_projects.title as project_title')
                    ->latest('financial_commitments.committed_at')->paginate(15)->withQueryString(),
                'disbursements' => DB::table('research_project_disbursements')
                    ->join('disbursements', 'disbursements.id', '=', 'research_project_disbursements.disbursement_id')
                    ->join('research_projects', 'research_projects.id', '=', 'research_project_disbursements.research_project_id')
                    ->whereIn('research_project_disbursements.research_project_id', clone $visibleProjectIds)
                    ->select('disbursements.*', 'research_projects.title as project_title', 'research_projects.currency')
                    ->latest('disbursements.created_at')->paginate(15)->withQueryString(),
            ],
            'documents' => ['documents' => DB::table('research_project_documents')
                ->join('documents', 'documents.id', '=', 'research_project_documents.document_id')
                ->join('research_projects', 'research_projects.id', '=', 'research_project_documents.research_project_id')
                ->whereIn('research_project_documents.research_project_id', clone $visibleProjectIds)
                ->select('documents.title', 'documents.document_type', 'research_project_documents.document_role', 'research_project_documents.created_at', 'research_projects.id as project_id', 'research_projects.title as project_title')
                ->latest('research_project_documents.created_at')->paginate(15)->withQueryString()],
            'notifications' => ['notifications' => DB::table('notifications')
                ->where('notifiable_type', User::class)->where('notifiable_id', $userId)
                ->latest()->paginate(20)->withQueryString()],
            'calendar' => [
                'calls' => DB::table('calls')->join('programs', 'programs.id', '=', 'calls.program_id')
                    ->where('calls.status', 'published')->whereDate('calls.closes_at', '>=', today())
                    ->select('calls.title', 'calls.opens_at', 'calls.closes_at', 'programs.name as program_name')
                    ->orderBy('calls.closes_at')->limit(20)->get(),
                'deadlines' => DB::table('research_projects')->whereIn('id', clone $visibleProjectIds)
                    ->whereNotNull('ends_at')->whereDate('ends_at', '>=', today())->orderBy('ends_at')->limit(20)->get(['id', 'title', 'ends_at']),
                'events' => DB::table('events')->where('status', 'published')->where('starts_at', '>=', now())
                    ->orderBy('starts_at')->limit(20)->get(['title', 'venue', 'starts_at', 'ends_at']),
            ],
        };

        return view('researcher.section', ['section' => $section, ...$data]);
    }

    public function calls(Request $request): View
    {
        $calls = DB::table('calls')
            ->join('programs', 'programs.id', '=', 'calls.program_id')
            ->where('calls.status', 'published')
            ->whereDate('calls.opens_at', '<=', today())
            ->whereDate('calls.closes_at', '>=', today())
            ->when($request->filled('domain'), fn ($query) => $query->where('calls.domains', 'like', '%'.$request->string('domain')->toString().'%'))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($query) => $query->where('calls.title', 'like', '%'.$request->string('q')->toString().'%')->orWhere('calls.description', 'like', '%'.$request->string('q')->toString().'%')))
            ->select('calls.*', 'programs.name as program_name')
            ->orderBy('calls.closes_at')
            ->paginate(12)
            ->withQueryString();

        return view('researcher.calls', compact('calls'));
    }

    public function createProjectForm(): View
    {
        return view('researcher.project-create', [
            'laboratories' => DB::table('laboratories')->where('status', 'active')->orderBy('name')->get(),
            'calls' => DB::table('calls')->where('status', 'published')->whereDate('opens_at', '<=', today())->whereDate('closes_at', '>=', today())->orderBy('closes_at')->get(),
        ]);
    }

    public function laboratory(): View
    {
        $profile = DB::table('researcher_profiles')->where('user_id', Auth::id())->firstOrFail();
        $laboratory = $profile->laboratory_id
            ? DB::table('laboratories')->leftJoin('universities', 'universities.id', '=', 'laboratories.university_id')->where('laboratories.id', $profile->laboratory_id)->select('laboratories.*', 'universities.name as university_name')->first()
            : null;
        $members = $laboratory
            ? DB::table('researcher_profiles')->join('users', 'users.id', '=', 'researcher_profiles.user_id')->where('researcher_profiles.laboratory_id', $laboratory->id)->select('users.name', 'researcher_profiles.research_domain', 'researcher_profiles.academic_rank')->orderBy('users.name')->get()
            : collect();
        $projects = $laboratory ? DB::table('research_projects')->where('laboratory_id', $laboratory->id)->latest()->get() : collect();

        return view('researcher.laboratory', compact('laboratory', 'members', 'projects'));
    }

    public function profile(): View
    {
        $profile = DB::table('researcher_profiles')->where('user_id', Auth::id())->first();
        $profileFields = ['phone', 'research_domain', 'speciality', 'academic_rank', 'university_id', 'laboratory_id', 'orcid', 'biography', 'position', 'keywords', 'department'];
        $completion = (int) round((collect($profileFields)->filter(fn (string $field): bool => filled($profile?->{$field} ?? null))->count() / count($profileFields)) * 100);

        return view('researcher.profile', [
            'profile' => $profile,
            'completion' => $completion,
            'universityName' => $profile?->university_id ? DB::table('universities')->where('id', $profile->university_id)->value('name') : null,
            'laboratoryName' => $profile?->laboratory_id ? DB::table('laboratories')->where('id', $profile->laboratory_id)->value('name') : null,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'orcid' => ['nullable', 'string', 'max:19'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'google_scholar_url' => ['nullable', 'url', 'max:255'],
            'researchgate_url' => ['nullable', 'url', 'max:255'],
            'department' => ['nullable', 'string', 'max:160'],
            'speciality' => ['nullable', 'string', 'max:255'],
            'research_domain' => ['nullable', 'string', 'max:255'],
            'academic_rank' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'university_id' => ['prohibited'],
            'laboratory_id' => ['prohibited'],
        ]);
        $profile = DB::table('researcher_profiles')->where('user_id', Auth::id())->first();
        DB::table('researcher_profiles')->updateOrInsert(
            ['user_id' => Auth::id()],
            [...$data, 'updated_at' => now(), 'created_at' => $profile?->created_at ?? now(), 'researcher_number' => $profile?->researcher_number ?? 'CH-'.strtoupper(Str::random(8))],
        );

        return back()->with('status', 'Profil chercheur mis à jour.');
    }

    public function uploadCv(Request $request): RedirectResponse
    {
        $request->validate(['cv' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document']]);
        $file = $request->file('cv');
        $documentId = $this->storeDocument($file, 'CV chercheur', 'researcher-cv');
        DB::table('researcher_profiles')->where('user_id', Auth::id())->update(['cv_document_id' => $documentId, 'updated_at' => now()]);

        return back()->with('status', 'CV téléversé.');
    }

    public function createProject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['nullable', 'string', 'max:10000'],
            'domain' => ['nullable', 'string', 'max:255'],
            'laboratory_id' => ['nullable', 'uuid', 'exists:laboratories,id'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:4'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $this->assertLaboratoryBelongsToUniversity($data['laboratory_id'] ?? null, Auth::id());
        $projectId = (string) Str::uuid();
        DB::table('research_projects')->insert([
            'id' => $projectId,
            'principal_researcher_id' => Auth::id(),
            'reference' => 'PRJ-'.strtoupper(Str::random(8)),
            'status' => 'draft',
            'currency' => 'FCFA',
            ...$data,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(\App\Services\AuditLogger::class)->record('researcher.project_created', 'research_projects', $projectId, [], ['principal_researcher_id' => Auth::id()]);

        return redirect()->route('researcher.projects.show', $projectId)->with('status', 'Projet créé.');
    }

    public function submitProject(string $project): RedirectResponse
    {
        $record = $this->ownedProject($project);
        abort_unless($record->status === 'draft', 422);
        $missingFields = [];
        foreach ([
            'title' => 'Le titre du projet est obligatoire.',
            'abstract' => 'Le résumé scientifique est obligatoire.',
            'domain' => 'Le domaine de recherche est obligatoire.',
            'budget' => 'Le budget demandé est obligatoire.',
        ] as $field => $message) {
            if (blank($record->{$field})) {
                $missingFields[$field] = $message;
            }
        }
        if ($missingFields !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($missingFields);
        }
        if ($record->research_program_id) {
            $programIsOpen = DB::table('research_programs')->where('id', $record->research_program_id)->where(function ($query): void {
                $query->whereNull('opens_at')->orWhereDate('opens_at', '<=', today());
            })->where(function ($query): void {
                $query->whereNull('closes_at')->orWhereDate('closes_at', '>=', today());
            })->exists();
            if (! $programIsOpen) {
                throw \Illuminate\Validation\ValidationException::withMessages(['research_program_id' => 'L’appel de recherche est fermé.']);
            }
        }
        DB::table('research_projects')->where('id', $record->id)->update(['status' => 'submitted', 'updated_at' => now()]);
        app(\App\Services\AuditLogger::class)->record('researcher.project_submitted', 'research_projects', $record->id, ['status' => 'draft'], ['status' => 'submitted']);
        app(\App\Services\NotificationService::class)->notify(Auth::user(), 'researcher.project_submitted', ['reference' => $record->reference], ['internal'], 'researcher.project_submitted:'.$record->id);

        return back()->with('status', 'Projet soumis pour évaluation.');
    }

    public function updateProject(Request $request, string $project): RedirectResponse
    {
        $record = $this->ownedProject($project);
        abort_unless(in_array($record->status, ['draft', 'complement'], true), 422);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['nullable', 'string', 'max:10000'],
            'domain' => ['nullable', 'string', 'max:255'],
            'laboratory_id' => ['nullable', 'uuid', 'exists:laboratories,id'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:4'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $this->assertLaboratoryBelongsToUniversity($data['laboratory_id'] ?? null, Auth::id());
        DB::table('research_projects')->where('id', $record->id)->update([...$data, 'updated_at' => now()]);

        return back()->with('status', 'Projet mis à jour.');
    }

    public function showProject(string $project): View
    {
        $record = $this->visibleProject($project);

        return view('researcher.project', [
            'project' => $record,
            'members' => DB::table('research_project_members')->join('users', 'users.id', '=', 'research_project_members.user_id')->where('research_project_id', $record->id)->select('users.name', 'users.email', 'research_project_members.role')->get(),
            'publications' => DB::table('research_publications')->where('research_project_id', $record->id)->latest()->get(),
            'documents' => DB::table('research_project_documents')->join('documents', 'documents.id', '=', 'research_project_documents.document_id')->where('research_project_id', $record->id)->select('documents.*', 'research_project_documents.document_role')->get(),
            'evaluations' => DB::table('research_project_evaluations')->join('users', 'users.id', '=', 'research_project_evaluations.evaluator_id')->where('research_project_id', $record->id)->select('users.name', 'research_project_evaluations.*')->get(),
            'criteria' => DB::table('evaluation_criteria')->orderBy('name')->get(),
        ]);
    }

        public function downloadProjectDocument(string $project, string $document): StreamedResponse
        {
            $record = $this->visibleProject($project);
            $documentRecord = DB::table('research_project_documents')
                ->join('documents', 'documents.id', '=', 'research_project_documents.document_id')
                ->where('research_project_documents.research_project_id', $record->id)
                ->where('research_project_documents.document_id', $document)
                ->where('documents.visibility', 'private')
                ->select('documents.*')
                ->first();

            abort_unless($documentRecord, 404);
            abort_unless(Storage::disk($documentRecord->disk)->exists($documentRecord->path), 404);

            return response()->streamDownload(
                fn () => print Storage::disk($documentRecord->disk)->get($documentRecord->path),
                basename($documentRecord->title),
                ['Content-Type' => $documentRecord->mime_type ?: 'application/octet-stream'],
            );
        }

    public function addMember(Request $request, string $project): RedirectResponse
    {
        $record = $this->ownedProject($project);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'role' => ['required', 'string', 'max:40']]);
        abort_unless(User::query()->whereKey($data['user_id'])->whereIn('account_type', ['chercheur', 'researcher'])->where('status', 'active')->exists(), 422, 'Le membre doit être un chercheur actif.');
        DB::table('research_project_members')->updateOrInsert(
            ['research_project_id' => $record->id, 'user_id' => $data['user_id']],
            ['id' => (string) Str::uuid(), 'role' => $data['role'], 'created_at' => now(), 'updated_at' => now()],
        );

        return back()->with('status', 'Membre ajouté.');
    }

    public function uploadProjectDocument(Request $request, string $project): RedirectResponse
    {
        $record = $this->ownedProject($project);
        $data = $request->validate(['document_role' => ['nullable', 'string', 'max:40'], 'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx', 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']]);
        $documentId = $this->storeDocument($request->file('file'), $request->file('file')->getClientOriginalName(), 'research-project');
        DB::table('research_project_documents')->insert(['id' => (string) Str::uuid(), 'research_project_id' => $record->id, 'document_id' => $documentId, 'document_role' => $data['document_role'] ?? null, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Document ajouté.');
    }

    public function createPublication(Request $request, string $project): RedirectResponse
    {
        $record = $this->ownedProject($project);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'doi' => ['nullable', 'string', 'max:120'], 'publication_type' => ['required', 'string', 'max:40'], 'published_on' => ['nullable', 'date'], 'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf', 'mimetypes:application/pdf']]);
        $documentId = isset($data['file']) ? $this->storeDocument($data['file'], $data['title'], 'publication') : null;
        unset($data['file']);
        DB::table('research_publications')->insert(['id' => (string) Str::uuid(), 'research_project_id' => $record->id, 'author_id' => Auth::id(), 'status' => 'draft', 'document_id' => $documentId, 'created_at' => now(), 'updated_at' => now(), ...$data]);

        return back()->with('status', 'Publication créée.');
    }

    public function tracking(string $project): View
    {
        $record = $this->visibleProject($project);
        $disbursements = DB::table('research_project_disbursements')
            ->join('disbursements', 'disbursements.id', '=', 'research_project_disbursements.disbursement_id')
            ->where('research_project_id', $record->id)
            ->select('disbursements.*')
            ->latest('scheduled_for')
            ->get();

        return view('researcher.tracking', [
            'project' => $record,
            'documents' => DB::table('research_project_documents')->join('documents', 'documents.id', '=', 'research_project_documents.document_id')->where('research_project_id', $record->id)->select('documents.*', 'research_project_documents.document_role')->latest('documents.created_at')->get(),
            'disbursements' => $disbursements,
        ]);
    }

    public function innovation(): View
    {
        return view('researcher.innovation', ['opportunities' => DB::table('innovation_programs')->join('programs', 'programs.id', '=', 'innovation_programs.program_id')->where('innovation_programs.status', 'active')->select('innovation_programs.*', 'programs.name as program_name')->orderBy('innovation_programs.closes_at')->paginate(12)]);
    }

    public function updatePublication(Request $request, string $publication): RedirectResponse
    {
        $record = DB::table('research_publications')->where('id', $publication)->where('author_id', Auth::id())->first();
        abort_unless($record, 404);
        abort_unless($record->status !== 'validated', 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'doi' => ['nullable', 'string', 'max:120'], 'publication_type' => ['required', 'string', 'max:40'], 'published_on' => ['nullable', 'date']]);
        DB::table('research_publications')->where('id', $record->id)->update([...$data, 'updated_at' => now()]);

        return back()->with('status', 'Publication mise à jour.');
    }

    public function publications(): View
    {
        $publications = DB::table('research_publications')->where('author_id', Auth::id())->when(request('q'), fn ($query, string $term) => $query->where(fn ($query) => $query->where('title', 'like', '%'.$term.'%')->orWhere('doi', 'like', '%'.$term.'%')))->when(request('status'), fn ($query, string $status) => $query->where('status', $status))->latest()->paginate(20)->withQueryString();
        return view('researcher.publications', compact('publications'));
    }

    public function publicPublications(): View
    {
        return view('researcher.publications', ['publications' => DB::table('research_publications')->where('status', 'validated')->latest()->get(), 'public' => true]);
    }

    public function conventions(): View
    {
        $projects = DB::table('research_projects')->where('principal_researcher_id', Auth::id())->pluck('id');
        return view('researcher.conventions', ['conventions' => DB::table('research_conventions')->whereIn('research_project_id', $projects)->latest()->get()]);
    }

    public function disbursements(): View
    {
        $projects = DB::table('research_projects')->where('principal_researcher_id', Auth::id())->pluck('id');
        return view('researcher.disbursements', ['disbursements' => DB::table('research_project_disbursements')->join('disbursements', 'disbursements.id', '=', 'research_project_disbursements.disbursement_id')->whereIn('research_project_id', $projects)->whereIn('disbursements.status', ['planned', 'disbursed'])->select('disbursements.*')->latest('disbursements.scheduled_for')->get()]);
    }

    private function ownedProject(string $id): object
    {
        $project = DB::table('research_projects')->where('id', $id)->where('principal_researcher_id', Auth::id())->first();
        abort_unless($project, 404);
        return $project;
    }

    private function visibleProject(string $id): object
    {
        $project = DB::table('research_projects')->where('id', $id)->first();
        $isMember = $project && DB::table('research_project_members')->where('research_project_id', $id)->where('user_id', Auth::id())->exists();
        abort_unless($project && ($project->principal_researcher_id === Auth::id() || $isMember), 404);
        return $project;
    }

    private function storeDocument(mixed $file, string $title, string $type): string
    {
        $id = (string) Str::uuid();
        DB::table('documents')->insert(['id' => $id, 'uploaded_by' => Auth::id(), 'title' => $title, 'document_type' => $type, 'disk' => 'local', 'path' => $file->store($type, 'local'), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'visibility' => 'private', 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    private function assertLaboratoryBelongsToUniversity(?string $laboratoryId, int $userId): void
    {
        if (! $laboratoryId) {
            return;
        }

        $profile = DB::table('researcher_profiles')->where('user_id', $userId)->first();
        $laboratory = DB::table('laboratories')->where('id', $laboratoryId)->first();
        abort_unless($laboratory && $profile?->university_id && $laboratory->university_id === $profile->university_id, 422, 'Le laboratoire doit appartenir à votre institution.');
    }
}
