<?php

namespace App\Http\Controllers;

use App\Enums\StudentApplicationStatus;
use App\Models\User;
use App\Services\StudentApplicationWorkflow;
use App\Services\AuditLogger;
use App\Services\NotificationService;
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
use Illuminate\Support\Facades\Mail;
use App\Mail\IneeCodeMail;

class StudentPortalController extends Controller
{
    public function register(): View
    {
        return view('student.auth', [
            'mode' => 'register',
            'universities' => Schema::hasTable('universities')
                ? DB::table('universities')->where('status', 'active')->orderBy('name')->pluck('name', 'id')
                : collect(),
        ]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:40'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', 'string', 'max:20'],
            'region' => ['nullable', 'string', 'max:100'],
            'university_id' => ['nullable', 'uuid', 'exists:universities,id'],
            'inee' => ['required', 'string', 'max:80', 'unique:student_profiles,inee'],
            'terms' => ['sometimes', 'accepted'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'email.unique' => 'Cette adresse email est déjà utilisée. Connectez-vous ou utilisez une autre adresse.',
            'inee.unique' => 'Ce code INEE est déjà utilisé.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->assignRole('etudiant');
        DB::table('student_profiles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'inee' => $data['inee'],
            'phone' => $data['phone'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'sex' => $data['sex'] ?? null,
            'region' => $data['region'] ?? null,
            'university_id' => $data['university_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            event(new Registered($user));
            app(NotificationService::class)->notify($user, 'account.created', [], ['internal', 'email'], 'account.created:'.$user->id);
            Mail::to($user->email)->send(new IneeCodeMail($user->name, $data['inee']));
        } catch (TransportExceptionInterface) {
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.dashboard')->with('status', 'Compte créé. Vérifiez votre adresse email.');
    }

    public function login(): View
    {
        return view('student.auth', ['mode' => 'login']);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['login' => ['required_without:email', 'nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'password' => ['required', 'string']]);
        $login = (string) ($credentials['login'] ?? $credentials['email'] ?? '');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'inee';

        if (! Auth::attempt([$field => $login, 'password' => $credentials['password'], 'status' => 'active'], $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'Les identifiants fournis sont incorrects.'])->onlyInput('login');
        }

        $request->session()->regenerate();
        $user = $request->user();

        if ($user->account_type === 'universite' && ! $user->university_id && ! DB::table('university_users')->where('user_id', $user->id)->exists()) {
            Auth::logout();
            return redirect()->route('student.login')->withErrors(['email' => 'Votre compte de Responsable universitaire n’est associé à aucune université. Veuillez contacter l’administrateur.']);
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
        $applications = DB::table('applications')->where('applicant_id', $userId)->latest()->get();
        $payments = DB::table('payment_records')
            ->join('disbursements', 'disbursements.id', '=', 'payment_records.disbursement_id')
            ->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id')
            ->join('applications', 'applications.id', '=', 'financial_commitments.application_id')
            ->leftJoin('programs', 'programs.id', '=', 'applications.program_id')
            ->where('applications.applicant_id', $userId)
            ->select('payment_records.*', 'programs.name as program_name')
            ->latest('payment_records.paid_at')->get();
        $missingDocuments = 0;
        foreach ($applications as $application) {
            $missingDocuments += count(app(StudentApplicationWorkflow::class)->missingDocuments($application->id, $application->program_id));
        }

        return view('student.dashboard-overview', [
            'applications' => $applications,
            'profile' => DB::table('student_profiles')->where('user_id', $userId)->select('*')->selectRaw('inee as student_number')->first(),
            'calls' => DB::table('calls')->where('status', 'published')->whereDate('closes_at', '>=', today())->latest()->limit(5)->get(),
            'notifications' => $user->notifications()->latest()->limit(5)->get(),
            'stats' => [
                'total' => $applications->count(),
                'active' => $applications->whereNotIn('status', ['rejete', 'archive', 'cloture'])->count(),
                'accepted' => $applications->whereIn('status', ['valide', 'decision', 'approuve', 'engage', 'decaisse', 'paye', 'archive'])->count(),
                'rejected' => $applications->where('status', 'rejete')->count(),
                'payments' => $payments->count(),
                'unread' => $user->notifications()->whereNull('read_at')->count(),
                'missing' => $missingDocuments,
            ],
            'payments' => $payments,
        ]);
    }

    public function payments(): View
    {
        $payments = DB::table('payment_records')
            ->join('disbursements', 'disbursements.id', '=', 'payment_records.disbursement_id')
            ->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id')
            ->join('applications', 'applications.id', '=', 'financial_commitments.application_id')
            ->leftJoin('programs', 'programs.id', '=', 'applications.program_id')
            ->where('applications.applicant_id', Auth::id())
            ->select('payment_records.*', 'programs.name as program_name')
            ->latest('payment_records.paid_at')->get();

        return view('student.payments', compact('payments'));
    }

    public function profile(): View
    {
        return view('student.profile', ['profile' => DB::table('student_profiles')->where('user_id', Auth::id())->first()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        DB::table('student_profiles')->where('user_id', Auth::id())->update([...$data, 'updated_at' => now()]);

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
            ->select('documents.*', 'application_documents.application_id', 'application_documents.status as document_status')
            ->latest('documents.created_at')->get();

        return view('student.documents', compact('documents'));
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

    public function support(): View
    {
        return view('student.support', [
            'claims' => DB::table('claims')->where('claimant_id', Auth::id())->latest()->get(),
            'threads' => DB::table('message_thread_users')->join('message_threads', 'message_threads.id', '=', 'message_thread_users.thread_id')->where('user_id', Auth::id())->latest('message_threads.updated_at')->get(),
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
        DB::transaction(function () use ($data, $threadId): void {
            DB::table('message_threads')->insert(['id' => $threadId, 'subject' => $data['subject'] ?? null, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('message_thread_users')->insert(['id' => (string) Str::uuid(), 'thread_id' => $threadId, 'user_id' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('messages')->insert(['id' => (string) Str::uuid(), 'thread_id' => $threadId, 'sender_id' => Auth::id(), 'body' => $data['body'], 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('status', 'Message envoyé.');
    }
}
