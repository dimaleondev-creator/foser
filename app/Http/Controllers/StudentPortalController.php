<?php

namespace App\Http\Controllers;

use App\Enums\StudentApplicationStatus;
use App\Models\User;
use App\Services\StudentApplicationWorkflow;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\StudentEligibilityService;
use App\Services\RoleDashboardResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class StudentPortalController extends Controller
{
    public function register(): View
    {
        return view('student.auth', ['mode' => 'register']);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:40'],
            'birth_place' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', 'string', 'max:20'],
            'region' => ['nullable', 'string', 'max:100'],
            'university_id' => ['prohibited'],
            'terms' => ['required', 'accepted'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'email.unique' => 'Cette adresse email est déjà utilisée. Connectez-vous ou utilisez une autre adresse.',
            'birth_place.required' => 'Le pays ou lieu de naissance est obligatoire.',
            'birth_place.max' => 'Le pays ou lieu de naissance ne peut pas dépasser 120 caractères.',
        ]);

        $pendingIne = $request->session()->get('ine.declaration.pending');
        if (! is_array($pendingIne)
            || ($pendingIne['user_id'] ?? null) !== null
            || ($pendingIne['expires_at'] ?? 0) < now()->timestamp
            || blank($pendingIne['inee'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'inee' => 'Déclarez et vérifiez votre INEE avant de créer votre compte étudiant.',
            ]);
        }

        $normalizedAccountName = Str::lower(Str::ascii(trim($data['name'])));
        $pendingNames = [
            Str::lower(Str::ascii(trim(($pendingIne['first_name'] ?? '').' '.($pendingIne['last_name'] ?? '')))),
            Str::lower(Str::ascii(trim(($pendingIne['last_name'] ?? '').' '.($pendingIne['first_name'] ?? '')))),
        ];
        if (! in_array($normalizedAccountName, $pendingNames, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'Le nom doit correspondre à l’identité déclarée avec votre INEE.',
            ]);
        }

        $user = DB::transaction(function () use ($data, $pendingIne): User {
            $alreadyAssociated = DB::table('student_profiles')
                ->whereRaw('LOWER(inee) = ?', [Str::lower($pendingIne['inee'])])
                ->exists();
            if ($alreadyAssociated) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'inee' => 'Cet INEE est déjà associé à un compte étudiant.',
                ]);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->assignRole('etudiant');
            DB::table('student_profiles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'first_name' => $pendingIne['first_name'],
                'last_name' => $pendingIne['last_name'],
                'inee' => strtoupper($pendingIne['inee']),
                'ine_status' => 'pending',
                'phone' => $data['phone'],
                'birth_place' => $data['birth_place'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'sex' => $data['sex'] ?? null,
                'region' => $data['region'] ?? null,
                'university_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        try {
            event(new Registered($user));
            app(NotificationService::class)->notify($user, 'account.created', [], ['internal', 'email'], 'account.created:'.$user->id);
        } catch (TransportExceptionInterface) {
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($request->session()->has('ine.declaration.pending')) {
            return redirect()->route('ine.declare')->with('status', 'Compte créé. Confirmez votre déclaration INE.');
        }

        return redirect()->route('student.dashboard')->with('status', 'Compte créé. Vérifiez votre adresse email.');
    }

    public function login(): View
    {
        return view('student.auth', ['mode' => 'login']);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['login' => ['required_without:email', 'nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'password' => ['required', 'string']]);
        $login = trim((string) ($credentials['login'] ?? $credentials['email'] ?? ''));
        $authenticated = false;
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $panelRoles = ['super_admin', 'admin', 'directeur_general', 'gestionnaire', 'agent_dossier', 'agent_finance', 'agent_recherche', 'agent_communication'];
            $staffLogin = DB::table('users')
                ->join('model_has_roles', function ($join): void {
                    $join->on('model_has_roles.model_id', '=', 'users.id')->where('model_has_roles.model_type', User::class);
                })
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('users.email', $login)
                ->whereIn('roles.name', $panelRoles)
                ->exists();

            $authenticated = ! $staffLogin && Auth::attempt(['email' => $login, 'password' => $credentials['password'], 'status' => 'active'], $request->boolean('remember'));
        } else {
            $profile = DB::table('student_profiles')
                ->whereRaw('LOWER(inee) = ?', [Str::lower($login)])
                ->whereIn('ine_status', ['pending', 'verified'])
                ->first();
            $user = $profile ? User::query()->whereKey($profile->user_id)->where('account_type', 'etudiant')->where('status', 'active')->first() : null;
            if ($user && $profile->ine_login_code_hash && Hash::check($credentials['password'], $profile->ine_login_code_hash)) {
                Auth::login($user, $request->boolean('remember'));
                $authenticated = true;
            }
        }

        if (! $authenticated) {
            return back()->withErrors(['login' => 'Les identifiants fournis sont incorrects.'])->onlyInput('login');
        }

        $request->session()->regenerate();
        $user = $request->user();

        if ($user->account_type === 'universite' && ! $user->university_id && ! DB::table('university_users')->where('user_id', $user->id)->exists()) {
            Auth::logout();
            return redirect()->route('student.login')->withErrors(['email' => 'Votre compte de Responsable universitaire n’est associé à aucune université. Veuillez contacter l’administrateur.']);
        }

        $pendingIneDeclaration = $request->session()->get('ine.declaration.pending');
        if ($user->account_type === 'etudiant'
            && $pendingIneDeclaration
            && (($pendingIneDeclaration['user_id'] ?? null) === null || (int) $pendingIneDeclaration['user_id'] === (int) $user->id)) {
            return redirect()->route('ine.declare');
        }

        return redirect()->route(app(RoleDashboardResolver::class)->routeFor($user));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('student.login');
    }

    public function dashboard(): View
    {
        $user = User::findOrFail(Auth::id());
        $userId = $user->id;
        $applications = DB::table('applications')
            ->leftJoin('programs', 'programs.id', '=', 'applications.program_id')
            ->where('applications.applicant_id', $userId)
            ->select('applications.*', 'programs.name as program_name')
            ->latest('applications.created_at')->get();
        $payments = DB::table('payment_records')
            ->join('disbursements', 'disbursements.id', '=', 'payment_records.disbursement_id')
            ->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id')
            ->join('applications', 'applications.id', '=', 'financial_commitments.application_id')
            ->leftJoin('programs', 'programs.id', '=', 'applications.program_id')
            ->where('applications.applicant_id', $userId)
            ->where('payment_records.status', 'paid')
            ->whereNotNull('payment_records.paid_at')
            ->select('payment_records.*', 'programs.name as program_name')
            ->latest('payment_records.paid_at')->get();
        $profile = DB::table('student_profiles')->where('user_id', $userId)->first();
        $university = $profile?->university_id
            ? DB::table('universities')->where('id', $profile->university_id)->value('name')
            : null;
        $documents = DB::table('application_documents')
            ->join('documents', 'documents.id', '=', 'application_documents.document_id')
            ->join('applications', 'applications.id', '=', 'application_documents.application_id')
            ->where('applications.applicant_id', $userId)
            ->select('documents.title', 'documents.document_type', 'application_documents.status', 'application_documents.updated_at', 'applications.reference')
            ->latest('application_documents.updated_at')->limit(5)->get();
        $awards = DB::table('application_awards')
            ->join('applications', 'applications.id', '=', 'application_awards.application_id')
            ->where('applications.applicant_id', $userId)
            ->where('application_awards.status', 'active');
        $awardedAmount = (float) (clone $awards)->sum('application_awards.amount');
        $calls = DB::table('calls')
            ->leftJoin('programs', 'programs.id', '=', 'calls.program_id')
            ->whereIn('calls.status', ['published', 'open'])
            ->whereDate('calls.opens_at', '<=', today())
            ->whereDate('calls.closes_at', '>=', today())
            ->select('calls.*', 'programs.name as program_name')
            ->orderBy('calls.closes_at')->limit(5)->get();
        $events = DB::table('events')
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')->limit(4)->get();
        $missingDocuments = 0;
        foreach ($applications as $application) {
            $missingDocuments += count(app(StudentApplicationWorkflow::class)->missingDocuments($application->id, $application->program_id));
        }
        $profileFields = ['phone', 'other_phone', 'national_id', 'nip', 'father_first_name', 'father_last_name', 'father_residence_country', 'father_function', 'mother_first_name', 'mother_last_name', 'mother_residence_country', 'mother_function', 'birth_place', 'date_of_birth', 'nationality', 'address', 'program', 'academic_year', 'sex', 'university_id'];
        $profileCompletion = $profile
            ? (int) round(collect($profileFields)->filter(fn (string $field): bool => filled($profile->{$field} ?? null))->count() / count($profileFields) * 100)
            : 0;
        $latestApplication = $applications->first();
        $history = $latestApplication
            ? DB::table('application_status_histories')->where('application_id', $latestApplication->id)->latest('changed_at')->limit(5)->get()
            : collect();
        $notifications = $user->notifications()->latest()->limit(5)->get();

        return view('student.dashboard-overview', [
            'user' => $user,
            'applications' => $applications,
            'profile' => $profile,
            'universityName' => $university,
            'documents' => $documents,
            'calls' => $calls,
            'events' => $events,
            'notifications' => $notifications,
            'history' => $history,
            'stats' => [
                'total' => $applications->count(),
                'active' => $applications->whereNotIn('status', ['rejete', 'archive', 'cloture'])->count(),
                'accepted' => $applications->whereIn('status', ['valide', 'decision', 'approuve', 'engage', 'decaisse', 'paye', 'archive'])->count(),
                'unread' => $user->notifications()->whereNull('read_at')->count(),
                'missing' => $missingDocuments,
                'total_received' => (float) $payments->sum('amount'),
                'awarded' => $awardedAmount,
                'pending_disbursement' => max(0, $awardedAmount - (float) $payments->sum('amount')),
                'profile_completion' => $profileCompletion,
            ],
            'payments' => $payments,
            'latestApplication' => $latestApplication,
        ]);
    }

    public function programs(Request $request): View
    {
        $programs = DB::table('programs')
            ->whereIn('programs.status', ['published', 'active'])
            ->when($request->filled('type'), fn ($query) => $query->where('programs.type', $request->string('type')->toString()))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($query) => $query->where('programs.name', 'like', '%'.$request->string('q')->toString().'%')->orWhere('programs.description', 'like', '%'.$request->string('q')->toString().'%')))
            ->orderBy('programs.name')
            ->paginate(12)
            ->withQueryString();

        return view('student.programs', ['programs' => $programs]);
    }

    public function eligibility(string $program): View
    {
        $record = DB::table('programs')->where('id', $program)->whereIn('status', ['published', 'active'])->firstOrFail();
        return view('student.eligibility', ['program' => $record, 'evaluation' => null]);
    }

    public function evaluateEligibility(Request $request, string $program, StudentEligibilityService $eligibility): View
    {
        $record = DB::table('programs')->where('id', $program)->whereIn('status', ['published', 'active'])->firstOrFail();
        $data = $request->validate([
            'student' => ['required', 'boolean'],
            'university_id' => ['nullable', 'uuid'],
            'level' => ['nullable', 'string', 'max:80'],
            'average' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'age' => ['nullable', 'integer', 'min:10', 'max:100'],
            'beneficiary' => ['required', 'boolean'],
            'loan_beneficiary' => ['required', 'boolean'],
        ]);

        return view('student.eligibility', ['program' => $record, 'evaluation' => $eligibility->evaluate(User::findOrFail(Auth::id()), $program, $data)]);
    }

    public function createApplicationForm(string $program): View
    {
        $record = DB::table('programs')->where('id', $program)->where('status', 'published')->firstOrFail();
        $calls = DB::table('calls')->where('program_id', $program)->where('status', 'published')->whereDate('opens_at', '<=', today())->whereDate('closes_at', '>=', today())->orderBy('closes_at')->get();
        return view('student.application-create', ['program' => $record, 'calls' => $calls]);
    }

    public function results(): View
    {
        $results = DB::table('application_results')
            ->join('applications', 'applications.id', '=', 'application_results.application_id')
            ->leftJoin('programs', 'programs.id', '=', 'applications.program_id')
            ->leftJoin('application_awards', 'application_awards.application_id', '=', 'applications.id')
            ->where('applications.applicant_id', Auth::id())
            ->whereNotNull('application_results.published_at')
            ->select('application_results.*', 'applications.reference', 'applications.submitted_at', 'programs.name as program_name', 'application_awards.amount as awarded_amount', 'application_awards.decision_reference')
            ->latest('application_results.published_at')
            ->get();

        return view('student.results', compact('results'));
    }

    public function notifications(): View
    {
        return view('student.notifications', [
            'notifications' => DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', Auth::id())
                ->latest()
                ->paginate(20),
        ]);
    }

    public function attestation(string $application): View
    {
        $attestation = DB::table('application_awards')
            ->join('applications', 'applications.id', '=', 'application_awards.application_id')
            ->join('programs', 'programs.id', '=', 'applications.program_id')
            ->join('users', 'users.id', '=', 'applications.applicant_id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->leftJoin('universities', 'universities.id', '=', 'student_profiles.university_id')
            ->where('applications.id', $application)
            ->where('applications.applicant_id', Auth::id())
            ->where('application_awards.status', 'active')
            ->select(
                'application_awards.amount', 'programs.currency', 'application_awards.award_date',
                'application_awards.decision_reference', 'applications.reference as application_reference',
                'programs.name as program_name', 'users.name as student_name', 'users.email as student_email',
                'student_profiles.inee', 'universities.name as university_name',
            )
            ->firstOrFail();

        return view('student.attestation', compact('attestation'));
    }

    public function markAllNotificationsAsRead(): RedirectResponse
    {
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Notifications marquées comme lues.');
    }

    public function payments(): View
    {
        $payments = DB::table('payment_records')
            ->join('disbursements', 'disbursements.id', '=', 'payment_records.disbursement_id')
            ->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id')
            ->join('applications', 'applications.id', '=', 'financial_commitments.application_id')
            ->leftJoin('programs', 'programs.id', '=', 'applications.program_id')
            ->where('applications.applicant_id', Auth::id())
            ->where('payment_records.status', 'paid')
            ->whereNotNull('payment_records.paid_at')
            ->select('payment_records.*', 'programs.name as program_name')
            ->latest('payment_records.paid_at')->get();

        return view('student.payments', compact('payments'));
    }

    public function profile(): View
    {
        $profile = DB::table('student_profiles')->where('user_id', Auth::id())->first();
        $profileFields = ['phone', 'other_phone', 'national_id', 'nip', 'father_first_name', 'father_last_name', 'father_residence_country', 'father_function', 'mother_first_name', 'mother_last_name', 'mother_residence_country', 'mother_function', 'date_of_birth', 'nationality', 'address', 'program', 'academic_year', 'region', 'province', 'sex', 'university_id'];
        $completed = collect($profileFields)->filter(fn (string $field): bool => filled($profile?->{$field} ?? null))->count();

        return view('student.profile', [
            'profile' => $profile,
            'university' => $profile?->university_id ? DB::table('universities')->where('id', $profile->university_id)->first() : null,
            'completion' => (int) round(($completed / count($profileFields)) * 100),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'other_phone' => ['nullable', 'string', 'max:40'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:120'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'nip' => ['nullable', 'string', 'max:20'],
            'father_first_name' => ['nullable', 'string', 'max:120'],
            'father_last_name' => ['nullable', 'string', 'max:120'],
            'father_residence_country' => ['nullable', 'string', 'max:100'],
            'father_function' => ['nullable', 'string', 'max:160'],
            'mother_first_name' => ['nullable', 'string', 'max:120'],
            'mother_last_name' => ['nullable', 'string', 'max:120'],
            'mother_residence_country' => ['nullable', 'string', 'max:100'],
            'mother_function' => ['nullable', 'string', 'max:160'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'faculty' => ['nullable', 'string', 'max:160'],
            'study_level' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'sex' => ['nullable', 'in:female,male,other'],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'university_id' => ['prohibited'],
        ]);
        $profile = DB::table('student_profiles')->where('user_id', Auth::id())->firstOrFail();
        $before = (array) $profile;
        DB::table('student_profiles')->where('user_id', Auth::id())->update([...$data, 'updated_at' => now()]);
        app(AuditLogger::class)->record('student.profile_updated', 'student_profiles', (string) ($before['id'] ?? ''), $before, $data);

        return back()->with('status', 'Profil mis à jour.');
    }

    public function applications(): View
    {
        $applications = DB::table('applications')->where('applicant_id', Auth::id())->latest()->get();

        foreach ($applications as $application) {
            $application->history = DB::table('application_status_histories')->where('application_id', $application->id)->orderBy('changed_at')->get();
            $application->missing_documents = app(StudentApplicationWorkflow::class)->missingDocuments($application->id, $application->program_id);
            $application->program_name = DB::table('programs')->where('id', $application->program_id)->value('name');
            $application->progress = StudentApplicationStatus::tryFrom($application->status)?->progress() ?? 0;
            $application->workflow_steps = $this->studentWorkflowSteps();
            $application->workflow_current_step = $this->studentWorkflowStepIndex($application);
            $application->responsible_agent = Schema::hasTable('application_assignments')
                ? DB::table('application_assignments')->where('application_id', $application->id)->join('users', 'users.id', '=', 'application_assignments.user_id')->value('users.name')
                : null;
        }

        return view('student.applications-timeline', compact('applications'));
    }

    public function createApplication(Request $request, StudentApplicationWorkflow $workflow): RedirectResponse
    {
        $workflow->createDraft(User::findOrFail(Auth::id()), $request->input('call_id'));

        return redirect()->route('student.applications')->with('status', 'Brouillon créé.');
    }

    public function editApplication(string $application): View
    {
        $record = DB::table('applications')->where('id', $application)->where('applicant_id', Auth::id())->firstOrFail();
        abort_unless(in_array($record->status, ['brouillon', 'complement'], true), 403);

        return view('student.application-edit', ['application' => $record]);
    }

    public function updateApplication(Request $request, string $application, StudentApplicationWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'project_title' => ['required', 'string', 'max:255'], 'summary' => ['required', 'string', 'max:2000'],
            'description' => ['required', 'string', 'max:10000'], 'domain' => ['required', 'string', 'max:255'],
            'objectives' => ['required', 'string', 'max:5000'], 'methodology' => ['required', 'string', 'max:10000'],
            'calendar' => ['required', 'string', 'max:5000'], 'budget' => ['required', 'numeric', 'min:0'],
            'team' => ['nullable', 'string', 'max:5000'], 'applicant_note' => ['nullable', 'string', 'max:5000'],
        ]);
        $workflow->updateDraft(User::findOrFail(Auth::id()), $application, $data);

        return redirect()->route('student.applications')->with('status', 'Brouillon enregistré.');
    }

    public function deleteApplication(string $application, StudentApplicationWorkflow $workflow): RedirectResponse
    {
        $workflow->deleteDraft(User::findOrFail(Auth::id()), $application);

        return back()->with('status', 'Brouillon supprimé.');
    }

    public function submitApplication(string $application, StudentApplicationWorkflow $workflow): RedirectResponse
    {
        $workflow->transition(User::findOrFail(Auth::id()), $application, StudentApplicationStatus::SOUMIS);

        return back()->with('status', 'Dossier soumis.');
    }

    public function requestComplement(string $application, StudentApplicationWorkflow $workflow): RedirectResponse
    {
        $workflow->transition(User::findOrFail(Auth::id()), $application, StudentApplicationStatus::COMPLEMENT);

        return back()->with('status', 'Demande de complément enregistrée.');
    }

    public function documents(): View
    {
        $documents = DB::table('application_documents')
            ->join('applications', 'applications.id', '=', 'application_documents.application_id')
            ->join('documents', 'documents.id', '=', 'application_documents.document_id')
            ->where('applications.applicant_id', Auth::id())
            ->select('documents.*', 'application_documents.application_id', 'application_documents.status as document_status', 'application_documents.comment as document_comment', 'application_documents.verified_at', 'applications.status as application_status')
            ->latest('documents.created_at')->get();

        $applicationId = request('application_id');
        $editableApplications = DB::table('applications')
            ->where('applicant_id', Auth::id())
            ->whereIn('status', ['brouillon', 'complement'])
            ->orderByDesc('updated_at')
            ->get(['id', 'reference', 'program_id']);
        $completeness = null;
        if ($applicationId) {
            $application = DB::table('applications')->where('id', $applicationId)->where('applicant_id', Auth::id())->first();
            abort_unless($application, 404);
            $required = DB::table('required_documents')->where('program_id', $application->program_id)->where('is_required', true)->pluck('document_type');
            $provided = DB::table('application_documents')->join('documents', 'documents.id', '=', 'application_documents.document_id')->where('application_id', $applicationId)->pluck('documents.document_type');
            $valid = DB::table('application_documents')->join('documents', 'documents.id', '=', 'application_documents.document_id')->where('application_id', $applicationId)->where('application_documents.status', 'validated')->pluck('documents.document_type');
            $completeness = ['required' => $required->count(), 'provided' => $provided->unique()->count(), 'valid' => $valid->unique()->count(), 'missing' => $required->diff($provided)->values()->all()];
        }

        return view('student.documents', compact('documents', 'completeness', 'editableApplications'));
    }

    public function downloadDocument(string $document): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $record = DB::table('documents')
            ->join('application_documents', 'application_documents.document_id', '=', 'documents.id')
            ->join('applications', 'applications.id', '=', 'application_documents.application_id')
            ->where('documents.id', $document)
            ->where('applications.applicant_id', Auth::id())
            ->select('documents.*')
            ->firstOrFail();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($record->disk);
        abort_unless($disk->exists($record->path), 404);
        app(AuditLogger::class)->record('application.document_downloaded', 'documents', $record->id, [], ['document_id' => $record->id]);

        return $disk->download($record->path, basename($record->title) . '.' . (pathinfo($record->path, PATHINFO_EXTENSION) ?: 'pdf'));
    }

    public function uploadDocument(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'application_id' => ['required', 'uuid'],
            'document_type' => ['required', 'string', 'max:80'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ]);
        $application = DB::table('applications')->where('id', $data['application_id'])->where('applicant_id', Auth::id())->first();
        abort_unless($application, 404);
        abort_unless(in_array($application->status, ['brouillon', 'complement'], true), 403);
        $this->assertDocumentTypeAllowed($application->program_id, $data['document_type']);

        $path = $request->file('file')->store('student-documents', 'local');
        $documentId = (string) Str::uuid();
        DB::table('documents')->insert([
            'id' => $documentId, 'uploaded_by' => Auth::id(), 'title' => $data['document_type'],
            'document_type' => $data['document_type'], 'disk' => 'local', 'path' => $path,
            'mime_type' => $request->file('file')->getMimeType(), 'size' => $request->file('file')->getSize(),
            'visibility' => 'private', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('application_documents')->insert([
            'id' => (string) Str::uuid(), 'application_id' => $application->id, 'document_id' => $documentId,
            'status' => 'submitted', 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(AuditLogger::class)->record('application.document_uploaded', 'application_documents', $documentId, [], ['application_id' => $application->id, 'document_type' => $data['document_type']]);

        return back()->with('status', 'Document téléversé.');
    }

    public function replaceDocument(Request $request, string $document): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ]);
        $record = DB::table('documents')
            ->join('application_documents', 'application_documents.document_id', '=', 'documents.id')
            ->join('applications', 'applications.id', '=', 'application_documents.application_id')
            ->where('documents.id', $document)
            ->where('documents.uploaded_by', Auth::id())
            ->where('applications.applicant_id', Auth::id())
            ->whereIn('applications.status', ['brouillon', 'complement'])
            ->select('documents.*', 'applications.id as application_id', 'applications.status as application_status')
            ->firstOrFail();

        $path = $request->file('file')->store('student-documents', 'local');
        try {
            DB::transaction(function () use ($request, $record, $path): void {
                DB::table('documents')->where('id', $record->id)->update([
                    'path' => $path,
                    'mime_type' => $request->file('file')->getMimeType(),
                    'size' => $request->file('file')->getSize(),
                    'checksum' => hash_file('sha256', $request->file('file')->getRealPath()),
                    'updated_at' => now(),
                ]);
                DB::table('application_documents')->where('application_id', $record->application_id)->where('document_id', $record->id)->update([
                    'status' => 'submitted', 'comment' => null, 'verified_by' => null, 'verified_at' => null, 'updated_at' => now(),
                ]);
                app(AuditLogger::class)->record('application.document_replaced', 'application_documents', $record->id, ['application_id' => $record->application_id], ['mime_type' => $request->file('file')->getMimeType(), 'size' => $request->file('file')->getSize()]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($record->path !== $path) {
            Storage::disk($record->disk)->delete($record->path);
        }

        return back()->with('status', 'Pièce remplacée. Elle sera contrôlée à nouveau.');
    }

    public function deleteDocument(string $document): RedirectResponse
    {
        $record = DB::table('documents')
            ->join('application_documents', 'application_documents.document_id', '=', 'documents.id')
            ->join('applications', 'applications.id', '=', 'application_documents.application_id')
            ->where('documents.id', $document)
            ->where('documents.uploaded_by', Auth::id())
            ->where('applications.applicant_id', Auth::id())
            ->whereIn('applications.status', ['brouillon', 'complement'])
            ->select('documents.*', 'applications.id as application_id')
            ->firstOrFail();

        DB::transaction(function () use ($record): void {
            DB::table('application_documents')->where('application_id', $record->application_id)->where('document_id', $record->id)->delete();
            DB::table('documents')->where('id', $record->id)->update(['deleted_at' => now(), 'updated_at' => now()]);
            app(AuditLogger::class)->record('application.document_deleted', 'application_documents', $record->id, ['application_id' => $record->application_id], []);
        });
        Storage::disk($record->disk)->delete($record->path);

        return back()->with('status', 'Pièce supprimée du brouillon.');
    }

    private function assertDocumentTypeAllowed(string $programId, string $documentType): void
    {
        $requiredTypes = DB::table('required_documents')
            ->where('program_id', $programId)
            ->where('is_required', true)
            ->pluck('document_type');

        if ($requiredTypes->isNotEmpty() && ! $requiredTypes->contains($documentType)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'document_type' => 'Ce type de pièce n’est pas demandé pour ce programme.',
            ]);
        }
    }

    private function studentWorkflowSteps(): array
    {
        return [
            'BROUILLON', 'PIÈCES', 'CONTRÔLE', 'SOUMIS', 'VÉRIFICATION UNIVERSITÉ',
            'VÉRIFICATION FOSER', 'ÉVALUATION', 'DÉCISION', 'ATTRIBUTION', 'DÉCAISSEMENT', 'TERMINÉ',
        ];
    }

    private function studentWorkflowStepIndex(object $application): int
    {
        return match ($application->status) {
            'brouillon', 'draft' => DB::table('application_documents')->where('application_id', $application->id)->exists() ? 2 : 0,
            'soumis', 'submitted' => 3,
            'verification', 'incomplet', 'complement', 'documents_pending', 'university_review' => 4,
            'recevable', 'eligible' => 5,
            'evaluation' => 6,
            'valide', 'decision', 'decision_made', 'result_published' => 7,
            'approuve', 'awarded' => 8,
            'engage', 'decaisse', 'committed', 'disbursement_pending', 'disbursed' => 9,
            'paye', 'archive', 'cloture', 'paid', 'archived', 'completed' => 10,
            'rejete', 'rejected' => 7,
            default => 0,
        };
    }

    public function support(): View
    {
        $threads = DB::table('message_thread_users')
            ->join('message_threads', 'message_threads.id', '=', 'message_thread_users.thread_id')
            ->where('message_thread_users.user_id', Auth::id())
            ->select('message_threads.*')
            ->latest('message_threads.updated_at')
            ->get();

        foreach ($threads as $thread) {
            $thread->messages = DB::table('messages')
                ->join('users', 'users.id', '=', 'messages.sender_id')
                ->where('messages.thread_id', $thread->id)
                ->select('messages.*', 'users.name as sender_name')
                ->orderBy('messages.created_at')
                ->get();
        }

        return view('student.support', [
            'claims' => DB::table('claims')->where('claimant_id', Auth::id())->latest()->get(),
            'threads' => $threads,
        ]);
    }

    public function claims(): View
    {
        return view('student.claims', [
            'claims' => DB::table('claims')->where('claimant_id', Auth::id())->latest()->get(),
        ]);
    }

    public function createClaim(Request $request): RedirectResponse
    {
        $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:5000']]);
        DB::table('claims')->insert([
            'id' => (string) Str::uuid(), 'claimant_id' => Auth::id(), 'reference' => 'REC-'.strtoupper(Str::random(8)),
            'subject' => $data['subject'], 'description' => $data['description'], 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'Réclamation créée.');
    }

    public function createThread(Request $request): RedirectResponse
    {
        $data = $request->validate(['subject' => ['nullable', 'string', 'max:255'], 'body' => ['required', 'string', 'max:5000']]);
        $threadId = (string) Str::uuid();
        $recipientId = DB::table('users')->whereIn('account_type', ['admin', 'super_admin'])->where('status', 'active')->value('id');
        abort_unless($recipientId, 503, 'Aucun destinataire FOSER disponible.');

        DB::transaction(function () use ($data, $threadId, $recipientId): void {
            DB::table('message_threads')->insert(['id' => $threadId, 'subject' => $data['subject'] ?? null, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('message_thread_users')->insert(['id' => (string) Str::uuid(), 'thread_id' => $threadId, 'user_id' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('message_thread_users')->insert(['id' => (string) Str::uuid(), 'thread_id' => $threadId, 'user_id' => $recipientId, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('messages')->insert(['id' => (string) Str::uuid(), 'thread_id' => $threadId, 'sender_id' => Auth::id(), 'body' => $data['body'], 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('status', 'Message envoyé.');
    }

    public function replyMessage(Request $request, string $thread): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $allowed = DB::table('message_thread_users')
            ->join('message_threads', 'message_threads.id', '=', 'message_thread_users.thread_id')
            ->where('message_thread_users.thread_id', $thread)
            ->where('message_thread_users.user_id', Auth::id())
            ->where('message_threads.status', 'open')
            ->exists();
        abort_unless($allowed, 404);

        DB::transaction(function () use ($data, $thread): void {
            DB::table('messages')->insert([
                'id' => (string) Str::uuid(), 'thread_id' => $thread, 'sender_id' => Auth::id(),
                'body' => $data['body'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('message_threads')->where('id', $thread)->update(['updated_at' => now()]);
        });

        return back()->with('status', 'Réponse envoyée.');
    }
}
