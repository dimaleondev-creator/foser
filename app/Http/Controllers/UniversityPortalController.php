<?php

namespace App\Http\Controllers;

use App\Services\UniversityStudentImportService;
use App\Services\UniversityApplicationValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;
use App\Models\University;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Filesystem\FilesystemAdapter;

class UniversityPortalController extends Controller
{
    public function dashboard(): View
    {
        $universityId = $this->universityId();
        $universityApplications = DB::table('applications')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')
            ->join('users', 'users.id', '=', 'applications.applicant_id')
            ->leftJoin('programs', 'programs.id', '=', 'applications.program_id')
            ->where('student_profiles.university_id', $universityId);

        return view('university.dashboard', [
            'university' => DB::table('universities')->where('id', $universityId)->first(),
            'studentsCount' => DB::table('student_profiles')->where('university_id', $universityId)->count(),
            'activeStudents' => DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')->where('student_profiles.university_id', $universityId)->where('users.status', 'active')->count(),
            'studentsPendingValidation' => DB::table('student_profiles')->where('university_id', $universityId)->where('validation_status', 'pending')->count(),
            'pendingApplications' => (clone $universityApplications)->whereIn('applications.status', ['soumis', 'verification', 'incomplet', 'complement'])->count(),
            'applicationsCount' => (clone $universityApplications)->count(),
            'validatedApplications' => (clone $universityApplications)->where('applications.status', 'eligible')->count(),
            'rejectedApplications' => (clone $universityApplications)->whereIn('applications.status', ['rejete', 'rejected'])->count(),
            'incompleteApplications' => (clone $universityApplications)->whereIn('applications.status', ['incomplet', 'complement'])->count(),
            'recentApplications' => (clone $universityApplications)
                ->select('applications.id', 'applications.reference', 'applications.status', 'applications.updated_at', 'users.name as student_name', 'programs.name as program_name')
                ->latest('applications.updated_at')->limit(6)->get(),
            'beneficiaries' => DB::table('application_awards')->join('applications', 'applications.id', '=', 'application_awards.application_id')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->where('student_profiles.university_id', $universityId)->distinct('application_awards.beneficiary_id')->count('application_awards.beneficiary_id'),
            'researchersCount' => DB::table('researcher_profiles')->where('university_id', $universityId)->count(),
            'researchProjectsCount' => DB::table('research_projects')->whereIn('laboratory_id', DB::table('laboratories')->where('university_id', $universityId)->select('id'))->count(),
            'openCalls' => DB::table('calls')->where('status', 'published')->whereDate('opens_at', '<=', today())->whereDate('closes_at', '>=', today())->latest('closes_at')->limit(5)->get(),
            'lastImport' => DB::table('university_imports')->where('university_id', $universityId)->latest()->first(),
            'notifications' => DB::table('notifications')->where('notifiable_type', 'App\\Models\\User')->where('notifiable_id', Auth::id())->latest()->limit(5)->get(),
            'unreadNotifications' => DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', Auth::id())->whereNull('read_at')->count(),
            'recentMessages' => DB::table('message_thread_users as own_participant')
                ->join('messages', 'messages.thread_id', '=', 'own_participant.thread_id')
                ->join('users as senders', 'senders.id', '=', 'messages.sender_id')
                ->where('own_participant.user_id', Auth::id())
                ->where('messages.sender_id', '!=', Auth::id())
                ->select('messages.body', 'messages.created_at', 'senders.name as sender_name', 'own_participant.thread_id')
                ->latest('messages.created_at')->limit(5)->get(),
            'unreadMessages' => DB::table('message_thread_users as own_participant')
                ->join('messages', 'messages.thread_id', '=', 'own_participant.thread_id')
                ->where('own_participant.user_id', Auth::id())
                ->where('messages.sender_id', '!=', Auth::id())
                ->whereNull('messages.read_at')->count(),
        ]);
    }

    public function profile(): View
    {
        return view('university.profile', ['university' => DB::table('universities')->where('id', $this->universityId())->first()]);
    }

    public function student(int $student): View
    {
        $record = $this->studentQuery()->where('student_profiles.user_id', $student)->firstOrFail();
        $applications = DB::table('applications')->join('programs', 'programs.id', '=', 'applications.program_id')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->where('applications.applicant_id', $student)->where('student_profiles.university_id', $this->universityId())->select('applications.id', 'applications.reference', 'applications.status', 'applications.updated_at', 'programs.name as program_name')->latest('applications.updated_at')->get();
        return view('university.student', compact('record', 'applications'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        Gate::authorize('update', University::findOrFail($this->universityId()));
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'short_name' => ['nullable', 'string', 'max:80'], 'institution_type' => ['required', 'in:public,private'], 'founded_at' => ['nullable', 'date', 'before_or_equal:today'], 'accreditation_number' => ['nullable', 'string', 'max:120'], 'country' => ['required', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:120'], 'region' => ['nullable', 'string', 'max:100'], 'province' => ['nullable', 'string', 'max:100'], 'commune' => ['nullable', 'string', 'max:120'], 'address' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:255'], 'website' => ['nullable', 'url', 'max:255'], 'responsible_name' => ['nullable', 'string', 'max:160'], 'responsible_function' => ['nullable', 'string', 'max:120'], 'responsible_phone' => ['nullable', 'string', 'max:40'], 'responsible_email' => ['nullable', 'email', 'max:255']]);
        DB::table('universities')->where('id', $this->universityId())->update([...$data, 'updated_at' => now()]);
        app(\App\Services\AuditLogger::class)->record('university.profile.updated', 'universities', $this->universityId(), [], [...$data, 'performed_by' => Auth::id()]);
        return back()->with('status', 'Profil établissement mis à jour.');
    }

    public function users(): View
    {
        $id = $this->universityId();
        $users = DB::table('university_users')
            ->join('users', 'users.id', '=', 'university_users.user_id')
            ->where('university_users.university_id', $id)
            ->select('users.id', 'users.name', 'users.email', 'university_users.role')
            ->orderBy('users.name')
            ->get();
        $availableUsers = DB::table('users')
            ->where('account_type', 'universite')
            ->whereNotIn('status', ['disabled', 'suspended', 'inactive'])
            ->whereNull('university_id')
            ->whereNotIn('id', DB::table('university_users')->select('user_id'))
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return view('university.users', compact('users', 'availableUsers'));
    }

    public function addUser(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'role' => ['required', 'in:admin_universite,validateur,gestionnaire_etudiants,responsable_recherche,lecture_seule']]);
        $universityId = $this->universityId();
        $target = DB::table('users')->where('id', $data['user_id'])->where('account_type', 'universite')->whereNotIn('status', ['disabled', 'suspended', 'inactive'])->first();
        abort_unless($target, 422, 'Seuls les comptes établissement actifs peuvent être habilités.');
        abort_unless(! in_array($target->account_type, ['admin', 'super_admin', 'directeur_general'], true), 403);
        DB::table('university_users')->updateOrInsert(['university_id' => $universityId, 'user_id' => $data['user_id']], ['id' => (string) Str::uuid(), 'role' => $data['role'], 'created_at' => now(), 'updated_at' => now()]);
        app(\App\Services\AuditLogger::class)->record('university.user.granted', 'university_users', (string) $data['user_id'], [], ['university_id' => $universityId, 'role' => $data['role'], 'performed_by' => Auth::id()]);
        return back()->with('status', 'Utilisateur habilité.');
    }

    public function removeUser(int $user): RedirectResponse
    {
        abort_unless($user !== Auth::id(), 422);
        DB::table('university_users')->where('university_id', $this->universityId())->where('user_id', $user)->delete();
        return back()->with('status', 'Habilitation retirée.');
    }

    public function students(): View
    {
        $query = $this->studentQuery();
        if ($search = request('q')) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('student_profiles.inee', 'like', "%{$search}%");
            });
        }
        if ($status = request('status')) $query->where('student_profiles.validation_status', $status);
        return view('university.students', ['students' => $query->latest('student_profiles.updated_at')->paginate(25)->withQueryString()]);
    }

    public function laboratories(): View
    {
        return view('university.laboratories', [
            'laboratories' => DB::table('laboratories')
                ->where('university_id', $this->universityId())
                ->where('status', 'active')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function addLaboratory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', 'unique:laboratories,code'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $universityId = $this->universityId();
        $laboratoryId = (string) Str::uuid();

        DB::transaction(function () use ($data, $universityId, $laboratoryId): void {
            DB::table('laboratories')->insert([
                'id' => $laboratoryId,
                'university_id' => $universityId,
                'name' => trim($data['name']),
                'code' => trim($data['code']),
                'description' => $data['description'] ?? null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            app(\App\Services\AuditLogger::class)->record('university.laboratory.created', 'laboratories', $laboratoryId, [], [
                'university_id' => $universityId,
                'performed_by' => Auth::id(),
                'name' => trim($data['name']),
                'code' => trim($data['code']),
            ]);
        });

        return back()->with('status', 'Laboratoire ajouté à votre université.');
    }

    public function validateStudent(int $student): RedirectResponse
    {
        $universityId = $this->universityId();
        $before = DB::table('student_profiles')->where('university_id', $universityId)->where('user_id', $student)->first();
        abort_unless($before, 404);
        $updated = DB::table('student_profiles')->where('university_id', $universityId)->where('user_id', $student)->update(['validation_status' => 'validated', 'updated_at' => now()]);
        abort_unless($updated, 404);
        app(\App\Services\AuditLogger::class)->record('university.student.validated', 'student_profiles', (string) $before->id, ['validation_status' => $before->validation_status], ['validation_status' => 'validated', 'university_id' => $universityId, 'performed_by' => Auth::id()]);
        app(\App\Services\NotificationService::class)->notify(User::findOrFail($student), 'student.university_profile_validated', ['university_id' => $universityId], ['internal'], 'university-student-validation:'.$student.':'.$universityId);
        return back()->with('status', 'Informations étudiant validées.');
    }

    public function applications(): View
    {
        $query = DB::table('applications')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->join('users', 'users.id', '=', 'applications.applicant_id')->where('student_profiles.university_id', $this->universityId())->select('applications.*', 'users.name as student_name', 'users.email');
        if ($search = request('q')) $query->where(function ($builder) use ($search): void { $builder->where('users.name', 'like', "%{$search}%")->orWhere('users.email', 'like', "%{$search}%")->orWhere('applications.reference', 'like', "%{$search}%"); });
        if ($status = request('status')) $query->where('applications.status', $status);
        $applications = $query->latest('applications.updated_at')->paginate(25)->withQueryString();
        return view('university.applications', compact('applications'));
    }

    public function applicationDetail(string $application): View
    {
        $universityId = $this->universityId();
        $record = DB::table('applications')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')
            ->join('users', 'users.id', '=', 'applications.applicant_id')
            ->leftJoin('programs', 'programs.id', '=', 'applications.program_id')
            ->where('applications.id', $application)
            ->where('student_profiles.university_id', $universityId)
            ->select('applications.*', 'users.name as student_name', 'users.email as student_email', 'student_profiles.inee', 'student_profiles.program as study_program', 'student_profiles.faculty', 'student_profiles.study_level', 'student_profiles.academic_year', 'student_profiles.validation_status as student_validation_status', 'programs.name as program_name')
            ->firstOrFail();
        Gate::authorize('viewApplication', [University::findOrFail($universityId), Application::findOrFail($application)]);

        $documents = DB::table('application_documents')
            ->join('documents', 'documents.id', '=', 'application_documents.document_id')
            ->where('application_documents.application_id', $application)
            ->select('documents.id', 'documents.title', 'documents.document_type', 'documents.size', 'documents.mime_type', 'application_documents.status as review_status', 'application_documents.comment', 'application_documents.verified_at')
            ->orderBy('documents.created_at')
            ->get();
        $history = DB::table('application_status_histories')->where('application_id', $application)->orderBy('changed_at')->get();

        return view('university.application', compact('record', 'documents', 'history'));
    }

    public function downloadApplicationDocument(string $application, string $document): StreamedResponse
    {
        $record = DB::table('documents')
            ->join('application_documents', 'application_documents.document_id', '=', 'documents.id')
            ->join('applications', 'applications.id', '=', 'application_documents.application_id')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')
            ->where('applications.id', $application)
            ->where('documents.id', $document)
            ->where('student_profiles.university_id', $this->universityId())
            ->select('documents.*')
            ->firstOrFail();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($record->disk ?: 'local');
        abort_unless($disk->exists($record->path), 404);
        app(\App\Services\AuditLogger::class)->record('university.application.document_downloaded', 'documents', $record->id, [], [
            'application_id' => $application, 'university_id' => $this->universityId(), 'performed_by' => Auth::id(),
        ]);

        return $disk->download($record->path, basename($record->title).'.'.(pathinfo($record->path, PATHINFO_EXTENSION) ?: 'pdf'));
    }

    public function validateApplication(string $application, UniversityApplicationValidationService $validation): RedirectResponse
    {
        $validation->validate(Auth::user(), $application, $this->universityId());
        return back()->with('status', 'Dossier validé par l’établissement et transmis au FOSER.');
    }

    public function rejectApplication(Request $request, string $application, UniversityApplicationValidationService $validation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $validation->reject(Auth::user(), $application, $this->universityId(), trim($data['reason']));
        return back()->with('status', 'Candidature rejetée avec motif.');
    }

    public function requestApplicationCorrection(Request $request, string $application, UniversityApplicationValidationService $validation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $validation->requestCorrection(Auth::user(), $application, $this->universityId(), trim($data['reason']));
        return back()->with('status', 'Correction demandée à l’étudiant.');
    }

    public function importStudents(Request $request, UniversityStudentImportService $service): View
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:xlsx,csv,txt']]);
        $universityId = $this->universityId();
        try {
            $report = $service->import($data['file'], $universityId, Auth::id());
        } catch (ValidationException $exception) {
            DB::table('university_imports')->insert(['id' => (string) Str::uuid(), 'university_id' => $universityId, 'imported_by' => Auth::id(), 'filename' => $data['file']->getClientOriginalName(), 'status' => 'failed', 'error_count' => 1, 'report' => json_encode(['imported' => 0, 'updated' => 0, 'rejected' => 0, 'errors' => 1, 'lines' => [['line' => 1, 'status' => 'error', 'message' => $exception->getMessage()]]]), 'created_at' => now(), 'updated_at' => now()]);
            throw $exception;
        }
        return view('university.import-report', compact('report'));
    }

    public function previewStudentImport(Request $request, UniversityStudentImportService $service): View
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:xlsx,csv,txt']]);
        return view('university.import-preview', ['rows' => $service->preview($data['file']), 'filename' => $data['file']->getClientOriginalName()]);
    }

    public function imports(): View
    {
        return view('university.imports', ['imports' => DB::table('university_imports')->where('university_id', $this->universityId())->latest()->get()]);
    }

    public function importTemplate(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'foser-students-');
        abort_unless($path !== false, 500, 'Impossible de générer le modèle Excel.');

        try {
            $writer = new \OpenSpout\Writer\XLSX\Writer();
            $writer->openToFile($path);
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['inee', 'name', 'email', 'phone', 'nationality', 'address', 'program', 'academic_year']));
            $writer->close();
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return response()->download($path, 'modele-etudiants-universite.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function reports(): View
    {
        $id = $this->universityId();
        return view('university.reports', ['stats' => $this->reportStats($id)]);
    }

    public function exportReports(): StreamedResponse
    {
        $stats = $this->reportStats($this->universityId());
        return response()->streamDownload(function () use ($stats): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Indicateur', 'Valeur']);
            foreach ($stats as $label => $value) fputcsv($handle, [$label, $value]);
            fclose($handle);
        }, 'rapport-universite-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function messages(): View
    {
        $threads = DB::table('message_threads')
            ->join('message_thread_users', 'message_thread_users.thread_id', '=', 'message_threads.id')
            ->where('message_thread_users.user_id', Auth::id())
            ->select('message_threads.*')
            ->latest('message_threads.updated_at')
            ->paginate(20);

        foreach ($threads as $thread) {
            $thread->messages = DB::table('messages')
                ->join('users', 'users.id', '=', 'messages.sender_id')
                ->where('messages.thread_id', $thread->id)
                ->select('messages.*', 'users.name as sender_name')
                ->orderBy('messages.created_at')
                ->get();
        }

        return view('university.messages', ['threads' => $threads]);
    }

    public function replyMessage(Request $request, string $thread): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $allowed = DB::table('message_thread_users')->where('thread_id', $thread)->where('user_id', Auth::id())->exists();
        abort_unless($allowed, 404);
        DB::transaction(function () use ($data, $thread): void {
            DB::table('messages')->insert(['id' => (string) Str::uuid(), 'thread_id' => $thread, 'sender_id' => Auth::id(), 'body' => $data['body'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('message_threads')->where('id', $thread)->update(['updated_at' => now()]);
        });
        return back()->with('status', 'Réponse envoyée.');
    }

    public function messageFoser(Request $request): RedirectResponse
    {
        $data = $request->validate(['subject' => ['nullable', 'string', 'max:255'], 'body' => ['required', 'string', 'max:5000']]);
        $threadId = (string) Str::uuid();
        $recipientId = DB::table('users')->whereIn('account_type', ['admin', 'super_admin'])->where('status', 'active')->value('id');
        abort_unless($recipientId, 503, 'Aucun destinataire FOSER disponible.');
        DB::transaction(function () use ($data, $threadId, $recipientId): void {
            DB::table('message_threads')->insert(['id' => $threadId, 'subject' => $data['subject'] ?? 'Message université', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('message_thread_users')->insert(['id' => (string) Str::uuid(), 'thread_id' => $threadId, 'user_id' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('message_thread_users')->insert(['id' => (string) Str::uuid(), 'thread_id' => $threadId, 'user_id' => $recipientId, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('messages')->insert(['id' => (string) Str::uuid(), 'thread_id' => $threadId, 'sender_id' => Auth::id(), 'body' => $data['body'], 'created_at' => now(), 'updated_at' => now()]);
        });
        return back()->with('status', 'Message envoyé au FOSER.');
    }

    private function universityId(): string
    {
        $id = Auth::user()?->university_id ?: DB::table('university_users')->where('user_id', Auth::id())->value('university_id');
        abort_unless($id, 403);
        return (string) $id;
    }

    private function reportStats(string $id): array
    {
        $applications = DB::table('applications')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->where('student_profiles.university_id', $id);
        $students = DB::table('student_profiles')->where('university_id', $id)->count();
        $validatedStudents = DB::table('student_profiles')->where('university_id', $id)->where('validation_status', 'validated')->count();
        $applicationCount = (clone $applications)->count();
        $validatedApplications = (clone $applications)->where('applications.status', 'eligible')->count();

        return [
            'students' => $students,
            'validated_students' => $validatedStudents,
            'applications' => $applicationCount,
            'validated_applications' => $validatedApplications,
            'Étudiants' => $students,
            'Étudiants validés' => $validatedStudents,
            'Dossiers' => $applicationCount,
            'Dossiers validés' => $validatedApplications,
            'Dossiers en attente' => (clone $applications)->whereIn('applications.status', ['soumis', 'verification', 'incomplet', 'complement'])->count(),
            'Imports effectués' => DB::table('university_imports')->where('university_id', $id)->count(),
        ];
    }

    private function studentQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')->where('student_profiles.university_id', $this->universityId())->select('student_profiles.id', 'student_profiles.user_id', 'student_profiles.university_id', 'student_profiles.inee', 'student_profiles.first_name', 'student_profiles.last_name', 'student_profiles.date_of_birth', 'student_profiles.nationality', 'student_profiles.phone', 'student_profiles.address', 'student_profiles.program', 'student_profiles.academic_year', 'student_profiles.validation_status', 'student_profiles.region', 'student_profiles.province', 'student_profiles.sex', 'student_profiles.birth_place', 'student_profiles.faculty', 'student_profiles.study_level', 'student_profiles.avatar_path', 'student_profiles.ine_status', 'student_profiles.ine_verified_at', 'student_profiles.created_at', 'student_profiles.updated_at', 'users.name', 'users.email');
    }
}
