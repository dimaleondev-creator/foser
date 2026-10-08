<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Http\Resources\UniversityStudentApiResource;
use App\Http\Resources\UniversityApplicationApiResource;
use App\Services\AuditLogger;
use App\Services\UniversityApplicationValidationService;
use App\Services\UniversityStudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UniversityWorkspaceController
{
    public function me(Request $request): JsonResponse
    {
        $universityId = $this->universityId($request);
        return response()->json(['data' => DB::table('universities')->where('id', $universityId)->firstOrFail()]);
    }

    public function students(Request $request): JsonResponse
    {
        $id = $this->universityId($request);
        $filters = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:40']]);
        $students = DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('student_profiles.university_id', $id)
            ->when($filters['search'] ?? null, fn ($query, string $term) => $query->where(fn ($search) => $search->where('users.name', 'like', "%{$term}%")->orWhere('users.email', 'like', "%{$term}%")))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('student_profiles.validation_status', $status))
            ->select('student_profiles.user_id', 'student_profiles.university_id', 'student_profiles.program', 'student_profiles.academic_year', 'student_profiles.faculty', 'student_profiles.study_level', 'student_profiles.validation_status', 'users.name', 'users.email', 'users.status as account_status')
            ->paginate($filters['per_page'] ?? 25)->withQueryString();
        return response()->json(['data' => UniversityStudentApiResource::collection($students->items()), 'meta' => ['current_page' => $students->currentPage(), 'last_page' => $students->lastPage(), 'per_page' => $students->perPage(), 'total' => $students->total()]]);
    }

    public function student(Request $request, int $student): JsonResponse
    {
        $id = $this->universityId($request);
        $record = DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where('student_profiles.university_id', $id)->where('student_profiles.user_id', $student)
            ->select('student_profiles.user_id', 'student_profiles.university_id', 'student_profiles.program', 'student_profiles.academic_year', 'student_profiles.faculty', 'student_profiles.study_level', 'student_profiles.validation_status', 'users.name', 'users.email', 'users.status as account_status')
            ->firstOrFail();
        return response()->json(['data' => new UniversityStudentApiResource($record)]);
    }

    public function applications(Request $request): JsonResponse
    {
        $id = $this->universityId($request);
        $filters = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'status' => ['nullable', 'string', 'max:40']]);
        $applications = DB::table('applications')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->where('student_profiles.university_id', $id)->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('applications.status', $status))->select('applications.id', 'applications.call_id', 'applications.program_id', 'applications.reference', 'applications.status', 'applications.submitted_at', 'applications.updated_at')->latest('applications.updated_at')->paginate($filters['per_page'] ?? 25)->withQueryString();
        return response()->json(['data' => UniversityApplicationApiResource::collection($applications->items()), 'meta' => ['current_page' => $applications->currentPage(), 'last_page' => $applications->lastPage(), 'per_page' => $applications->perPage(), 'total' => $applications->total()]]);
    }

    public function validateApplication(Request $request, string $application, UniversityApplicationValidationService $validation): JsonResponse
    {
        $id = $this->universityId($request);
        $result = $validation->validate($request->user(), $application, $id);
        return response()->json(['data' => $result]);
    }

    public function requestCorrection(Request $request, string $application, UniversityApplicationValidationService $validation): JsonResponse
    {
        $id = $this->universityId($request);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $record = $validation->requestCorrection($request->user(), $application, $id, trim($data['reason']));
        return response()->json(['data' => ['id' => $record->id, 'status' => 'complement']]);
    }

    public function rejectApplication(Request $request, string $application, UniversityApplicationValidationService $validation): JsonResponse
    {
        $id = $this->universityId($request);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $record = $validation->reject($request->user(), $application, $id, trim($data['reason']));
        return response()->json(['data' => ['id' => $record->id, 'status' => 'rejected']]);
    }

    public function imports(Request $request, UniversityStudentImportService $service): JsonResponse
    {
        $id = $this->universityId($request);
        $data = $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:xlsx,csv,txt']]);
        return response()->json(['data' => $service->import($data['file'], $id, $request->user()->id)], 201);
    }

    public function statistics(Request $request): JsonResponse
    {
        $id = $this->universityId($request);
        $applications = DB::table('applications')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->where('student_profiles.university_id', $id);
        return response()->json(['data' => ['students' => DB::table('student_profiles')->where('university_id', $id)->count(), 'applications' => (clone $applications)->count(), 'validated_applications' => (clone $applications)->where('applications.status', 'eligible')->count(), 'rejected_applications' => (clone $applications)->whereIn('applications.status', ['rejete', 'rejected'])->count(), 'beneficiaries' => DB::table('application_awards')->join('applications', 'applications.id', '=', 'application_awards.application_id')->join('student_profiles', 'student_profiles.user_id', '=', 'applications.applicant_id')->where('student_profiles.university_id', $id)->distinct('application_awards.beneficiary_id')->count('application_awards.beneficiary_id')]]);
    }

    private function universityId(Request $request): string
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->account_type === 'universite', 403);
        $id = $user->university_id ?: DB::table('university_users')->where('user_id', $user->id)->value('university_id');
        abort_unless($id, 403);
        return (string) $id;
    }
}
