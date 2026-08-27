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

class UniversityPortalController extends Controller
{
    public function dashboard(): View
    {
        $universityId = $this->universityId();
        return view('university.dashboard', [
            'university' => DB::table('universities')->where('id', $universityId)->first(),
            'studentsCount' => DB::table('student_profiles')->where('university_id', $universityId)->count(),
            'pendingStudents' => DB::table('student_profiles')->where('university_id', $universityId)->where('validation_status', 'pending')->count(),
            'applicationsCount' => DB::table('applications')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->where('student_profiles.university_id', $universityId)->count(),
            'lastImport' => DB::table('university_imports')->where('university_id', $universityId)->latest()->first(),
            'notifications' => DB::table('notifications')->where('notifiable_type', 'App\\Models\\User')->where('notifiable_id', Auth::id())->latest()->limit(5)->get(),
        ]);
    }

    public function profile(): View
    {
        return view('university.profile', ['university' => DB::table('universities')->where('id', $this->universityId())->first()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'short_name' => ['nullable', 'string', 'max:80'], 'country' => ['required', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:120'], 'website' => ['nullable', 'url', 'max:255']]);
        DB::table('universities')->where('id', $this->universityId())->update([...$data, 'updated_at' => now()]);
        return back()->with('status', 'Profil établissement mis à jour.');
    }

    public function users(): View
    {
        $id = $this->universityId();
        return view('university.users', ['users' => DB::table('university_users')->join('users', 'users.id', '=', 'university_users.user_id')->where('university_id', $id)->select('users.id', 'users.name', 'users.email', 'university_users.role')->orderBy('users.name')->get()]);
    }

    public function addUser(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'role' => ['required', 'string', 'max:40']]);
        DB::table('university_users')->updateOrInsert(['university_id' => $this->universityId(), 'user_id' => $data['user_id']], ['id' => (string) Str::uuid(), 'role' => $data['role'], 'created_at' => now(), 'updated_at' => now()]);
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
        $updated = DB::table('student_profiles')->where('university_id', $this->universityId())->where('user_id', $student)->update(['validation_status' => 'validated', 'updated_at' => now()]);
        abort_unless($updated, 404);
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

    public function validateApplication(string $application, UniversityApplicationValidationService $validation): RedirectResponse
    {
        $validation->validate(Auth::user(), $application, $this->universityId());
        return back()->with('status', 'Dossier validé par l’établissement et transmis au FOSER.');
    }

    public function importStudents(Request $request, UniversityStudentImportService $service): View
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:xlsx']]);
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
        $data = $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:xlsx']]);
        return view('university.import-preview', ['rows' => $service->preview($data['file']), 'filename' => $data['file']->getClientOriginalName()]);
    }

    public function imports(): View
    {
        return view('university.imports', ['imports' => DB::table('university_imports')->where('university_id', $this->universityId())->latest()->get()]);
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
        return DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')->where('student_profiles.university_id', $this->universityId())->select('student_profiles.*', 'users.name', 'users.email');
    }
}
