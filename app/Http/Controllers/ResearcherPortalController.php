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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ResearcherPortalController extends Controller
{
    public function register(): View
    {
        return view('researcher.auth', ['mode' => 'register', 'universities' => DB::table('universities')->where('status', 'active')->orderBy('name')->get(), 'laboratories' => DB::table('laboratories')->where('status', 'active')->orderBy('name')->get()]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', 'min:8'],
            'phone' => ['required', 'string', 'max:40'], 'country' => ['required', 'string', 'max:100'], 'region' => ['nullable', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:100'],
            'research_domain' => ['required', 'string', 'max:255'], 'speciality' => ['required', 'string', 'max:255'], 'academic_rank' => ['nullable', 'string', 'max:255'],
            'university_id' => ['nullable', 'uuid', 'exists:universities,id'], 'laboratory_id' => ['nullable', 'uuid', 'exists:laboratories,id'], 'position' => ['nullable', 'string', 'max:150'],
            'orcid' => ['nullable', 'string', 'max:19'], 'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'], 'biography' => ['nullable', 'string', 'max:10000'], 'main_publications' => ['nullable', 'string', 'max:10000'], 'terms' => ['accepted'],
        ]);
        $user = User::create(['name' => trim($data['first_name'].' '.$data['last_name']), 'email' => $data['email'], 'password' => $data['password'], 'account_type' => 'researcher', 'status' => 'pending']);
        $reference = 'CHR-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        DB::table('researcher_profiles')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'researcher_number' => $reference, 'registration_reference' => $reference, 'status' => 'pending', 'phone' => $data['phone'], 'country' => $data['country'], 'region' => $data['region'] ?? null, 'city' => $data['city'] ?? null, 'research_domain' => $data['research_domain'], 'speciality' => $data['speciality'], 'academic_rank' => $data['academic_rank'] ?? null, 'university_id' => $data['university_id'] ?? null, 'laboratory_id' => $data['laboratory_id'] ?? null, 'position' => $data['position'] ?? null, 'orcid' => $data['orcid'] ?? null, 'years_experience' => $data['years_experience'] ?? null, 'biography' => $data['biography'] ?? null, 'main_publications' => $data['main_publications'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
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
        abort_unless($admin->can('research.manage'), 403);
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
        $projects = DB::table('research_projects')
            ->where(function ($query) use ($userId): void {
                $query->where('principal_researcher_id', $userId)
                    ->orWhereIn('id', DB::table('research_project_members')->where('user_id', $userId)->select('research_project_id'));
            })
            ->latest()->get();
        $projectIds = $projects->pluck('id');
        $commitments = DB::table('financial_commitments')->whereIn('research_project_id', $projectIds);
        $disbursements = DB::table('research_project_disbursements')
            ->join('disbursements', 'disbursements.id', '=', 'research_project_disbursements.disbursement_id')
            ->whereIn('research_project_disbursements.research_project_id', $projectIds);

        return view('researcher.dashboard', [
            'projects' => $projects,
            'publications' => DB::table('research_publications')->where('author_id', $userId)->latest()->limit(5)->get(),
            'profile' => DB::table('researcher_profiles')->where('user_id', $userId)->first(),
            'stats' => [
                'submitted_projects' => $projects->whereIn('status', ['submitted', 'verification', 'under_review', 'evaluation', 'accepted', 'rejected', 'funded'])->count(),
                'draft_projects' => $projects->where('status', 'draft')->count(),
                'evaluation_projects' => $projects->whereIn('status', ['under_review', 'evaluation'])->count(),
                'accepted_projects' => $projects->whereIn('status', ['accepted', 'funded'])->count(),
                'rejected_projects' => $projects->whereIn('status', ['rejected'])->count(),
                'funded_projects' => $projects->where('status', 'funded')->count(),
                'requested_amount' => (float) $projects->sum('budget'),
                'awarded_amount' => (float) (clone $commitments)->whereIn('status', ['approved', 'valide', 'execute'])->sum('amount'),
                'disbursed_amount' => (float) (clone $disbursements)->whereIn('disbursements.status', ['disbursed', 'execute', 'paid'])->sum('disbursements.amount'),
                'publications' => (int) DB::table('research_publications')->where('author_id', $userId)->count(),
                'active_conventions' => (int) DB::table('research_conventions')->whereIn('research_project_id', $projectIds)->whereIn('status', ['signed', 'active'])->count(),
                'unread_notifications' => (int) DB::table('notifications')->where('notifiable_type', 'App\\Models\\User')->where('notifiable_id', $userId)->whereNull('read_at')->count(),
                'pending_actions' => $projects->whereIn('status', ['draft', 'complement'])->count(),
            ],
        ]);
    }

    public function profile(): View
    {
        return view('researcher.profile', [
            'profile' => DB::table('researcher_profiles')->where('user_id', Auth::id())->first(),
            'universities' => DB::table('universities')->where('status', 'active')->orderBy('name')->get(),
            'laboratories' => DB::table('laboratories')->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'orcid' => ['nullable', 'string', 'max:19'],
            'speciality' => ['nullable', 'string', 'max:255'],
            'research_domain' => ['nullable', 'string', 'max:255'],
            'academic_rank' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'university_id' => ['nullable', 'uuid', 'exists:universities,id'],
            'laboratory_id' => ['nullable', 'uuid', 'exists:laboratories,id'],
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
            'laboratory_id' => ['nullable', 'uuid', 'exists:laboratories,id'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
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
            'currency' => 'GNF',
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
        abort_unless(filled($record->title) && filled($record->abstract) && filled($record->domain) && $record->budget !== null, 422, 'Le projet est incomplet : titre, résumé, domaine et budget sont obligatoires.');
        if ($record->research_program_id) {
            abort_unless(DB::table('research_programs')->where('id', $record->research_program_id)->where(function ($query): void {
                $query->whereNull('opens_at')->orWhereDate('opens_at', '<=', today());
            })->where(function ($query): void {
                $query->whereNull('closes_at')->orWhereDate('closes_at', '>=', today());
            })->exists(), 422, 'L’appel de recherche est fermé.');
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
            'laboratory_id' => ['nullable', 'uuid', 'exists:laboratories,id'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
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
        return view('researcher.publications', ['publications' => DB::table('research_publications')->where('author_id', Auth::id())->latest()->get()]);
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
        abort_unless($laboratory && (! $profile?->university_id || $laboratory->university_id === $profile->university_id), 422, 'Le laboratoire doit appartenir à votre institution.');
    }
}
