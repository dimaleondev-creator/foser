<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardStatisticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\UniversityController;
use App\Http\Controllers\Api\V1\ResearcherController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EvaluationController;
use App\Http\Controllers\Api\V1\ResearchProjectController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\FinancialOperationsController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ClaimController;
use App\Http\Controllers\Api\V1\StatisticsController;
use App\Http\Controllers\Api\V1\DocumentDownloadController;
use App\Http\Controllers\Api\V1\RegionalStatisticsController;
use App\Http\Controllers\Api\V1\GlobalSearchController;
use App\Http\Controllers\Api\V1\PublicCatalogController;
use App\Http\Controllers\Api\CandidateApplicationController;
use App\Http\Controllers\Api\EvaluatorAssignmentController;
use App\Http\Controllers\Api\ApplicationWorkflowApiController;
use App\Http\Controllers\Api\V1\ResearcherWorkspaceController;
use App\Http\Controllers\Api\V1\UniversityWorkspaceController;
use App\Http\Controllers\Api\V1\DecisionStatisticsController;
use App\Http\Controllers\Api\V1\FinancialRecordsController;
use App\Http\Controllers\AssistantController;

Route::get('/user', function (Request $request) {
    return response()->json(['data' => new \App\Http\Resources\UserResource($request->user())]);
})->middleware('auth:sanctum');

Route::get('/statistics', DashboardStatisticsController::class)
    ->middleware(['auth:sanctum', 'permission:reports.view'])
    ->name('api.statistics');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('auth.login');
    Route::get('/statistics/regions', RegionalStatisticsController::class)->middleware('throttle:api')->name('statistics.regions');
    Route::get('/search', GlobalSearchController::class)->middleware('throttle:api')->name('search');
    Route::get('/events', [PublicCatalogController::class, 'events'])->middleware('throttle:api')->name('events.index');
    Route::get('/partners', [PublicCatalogController::class, 'partners'])->middleware('throttle:api')->name('partners.index');
    Route::get('/testimonials', [PublicCatalogController::class, 'testimonials'])->middleware('throttle:api')->name('testimonials.index');
    Route::get('/news', [PublicCatalogController::class, 'news'])->middleware('throttle:api')->name('news.index');
    Route::get('/news/{slug}', [PublicCatalogController::class, 'newsDetail'])->middleware('throttle:api')->name('news.show');
    Route::get('/public/documents', [PublicCatalogController::class, 'documents'])->middleware('throttle:api')->name('documents.public.index');
    Route::post('/assistant/feedback', [AssistantController::class, 'feedback'])->middleware(['auth:sanctum', 'throttle:api'])->name('assistant.feedback');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::get('/users/me', [AuthController::class, 'me'])->name('users.me');
        Route::delete('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/results', [FinancialRecordsController::class, 'results'])->middleware('permission:applications.view')->name('results.index');
        Route::get('/awards', [FinancialRecordsController::class, 'awards'])->name('awards.index');
        Route::get('/disbursements', [FinancialRecordsController::class, 'disbursements'])->middleware('permission:finance.view')->name('disbursements.index');

        Route::get('/students', [StudentController::class, 'index'])->middleware('permission:users.view')->name('students.index');
        Route::get('/students/{id}', [StudentController::class, 'show'])->middleware('permission:users.view')->name('students.show');
        Route::get('/universities', [UniversityController::class, 'index'])->middleware('permission:university.view')->name('universities.index');
        Route::get('/universities/{id}', [UniversityController::class, 'show'])->middleware('permission:university.view')->name('universities.show');
        Route::get('/researchers', [ResearcherController::class, 'index'])->middleware('permission:research.view')->name('researchers.index');
        Route::get('/researchers/{id}', [ResearcherController::class, 'show'])->whereUuid('id')->middleware('permission:research.view')->name('researchers.show');
        Route::get('/programs', [ProgramController::class, 'index'])->middleware('permission:programs.view')->name('programs.index');
        Route::get('/programs/{id}', [ProgramController::class, 'show'])->middleware('permission:programs.view')->name('programs.show');
        Route::get('/calls', [CallController::class, 'index'])->middleware('permission:calls.view')->name('calls.index');
        Route::get('/calls/{id}', [CallController::class, 'show'])->middleware('permission:calls.view')->name('calls.show');
        Route::get('/applications', [ApplicationController::class, 'index'])->middleware('permission:applications.view')->name('applications.index');
        Route::get('/applications/{id}', [ApplicationController::class, 'show'])->middleware('permission:applications.view')->name('applications.show');
        Route::get('/documents', [DocumentController::class, 'index'])->middleware('permission:documents.view')->name('documents.index');
        Route::get('/documents/{document}/download', DocumentDownloadController::class)->middleware('permission:documents.view')->name('documents.download');
        Route::get('/documents/{id}', [DocumentController::class, 'show'])->middleware('permission:documents.view')->name('documents.show');
        Route::get('/evaluations', [EvaluationController::class, 'index'])->middleware('permission:evaluations.view')->name('evaluations.index');
        Route::get('/evaluations/{id}', [EvaluationController::class, 'show'])->middleware('permission:evaluations.view')->name('evaluations.show');
        Route::get('/research-projects', [ResearchProjectController::class, 'index'])->middleware('permission:research.view')->name('research-projects.index');
        Route::get('/research-projects/{id}', [ResearchProjectController::class, 'show'])->middleware('permission:research.view')->name('research-projects.show');
        Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:finance.view')->name('payments.index');
        Route::get('/payments/{id}', [PaymentController::class, 'show'])->middleware('permission:finance.view')->name('payments.show');
        Route::post('/disbursements/{disbursement}/payments', [FinancialOperationsController::class, 'initiate'])->name('payments.initiate');
        Route::post('/payments/{payment}/transition', [FinancialOperationsController::class, 'transition'])->name('payments.transition');
        Route::post('/payments/{payment}/reconciliations', [FinancialOperationsController::class, 'reconcile'])->name('payments.reconcile');
        Route::get('/payments/{payment}/reconciliations', [FinancialOperationsController::class, 'reconciliations'])->middleware('permission:finance.view')->name('payments.reconciliations');
        Route::post('/disbursements/{disbursement}/supporting-document', [FinancialOperationsController::class, 'uploadDisbursementDocument'])->name('disbursements.documents.store');
        Route::get('/transactions', [FinancialOperationsController::class, 'transactions'])->middleware('permission:finance.view')->name('transactions.index');
        Route::get('/financial-history', [FinancialOperationsController::class, 'history'])->middleware('permission:finance.view')->name('financial-history.index');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}', [NotificationController::class, 'show'])->name('notifications.show');
        Route::get('/claims', [ClaimController::class, 'index'])->middleware('permission:applications.view')->name('claims.index');
        Route::get('/claims/{id}', [ClaimController::class, 'show'])->middleware('permission:applications.view')->name('claims.show');
        Route::get('/statistics', StatisticsController::class)->middleware('permission:reports.view')->name('statistics');
        Route::prefix('statistics')->middleware('permission:view_statistics')->group(function (): void {
            Route::get('/overview', [DecisionStatisticsController::class, 'overview'])->name('statistics.overview');
            Route::get('/applications', [DecisionStatisticsController::class, 'applications'])->name('statistics.applications');
            Route::get('/financial', [DecisionStatisticsController::class, 'financial'])->middleware('permission:view_financial_statistics')->name('statistics.financial');
            Route::get('/programs', [DecisionStatisticsController::class, 'programs'])->name('statistics.programs');
            Route::get('/universities', [DecisionStatisticsController::class, 'universities'])->middleware('permission:view_university_statistics')->name('statistics.universities');
            Route::get('/gender', [DecisionStatisticsController::class, 'gender'])->name('statistics.gender');
            Route::get('/research', [DecisionStatisticsController::class, 'research'])->middleware('permission:view_research_statistics')->name('statistics.research');
            Route::get('/trends', [DecisionStatisticsController::class, 'trends'])->name('statistics.trends');
            Route::get('/loans', [DecisionStatisticsController::class, 'loans'])->middleware('permission:view_financial_statistics')->name('statistics.loans');
            Route::get('/export', [DecisionStatisticsController::class, 'export'])->middleware('permission:export_statistics')->name('statistics.export');
        });
    });

    Route::prefix('researchers')->middleware(['auth:sanctum', 'role.dashboard:chercheur,researcher', 'permission:research.view', 'throttle:api'])->group(function (): void {
        Route::get('/me', [ResearcherWorkspaceController::class, 'me'])->name('researchers.me');
        Route::get('/projects', [ResearcherWorkspaceController::class, 'projects'])->name('researchers.projects');
        Route::post('/projects', [ResearcherWorkspaceController::class, 'storeProject'])->middleware('permission:research.manage')->name('researchers.projects.store');
        Route::get('/projects/{project}', [ResearcherWorkspaceController::class, 'showProject'])->name('researchers.projects.show');
        Route::put('/projects/{project}', [ResearcherWorkspaceController::class, 'updateProject'])->middleware('permission:research.manage')->name('researchers.projects.update');
        Route::post('/projects/{project}/submit', [ResearcherWorkspaceController::class, 'submitProject'])->middleware('permission:research.manage')->name('researchers.projects.submit');
        Route::get('/publications', [ResearcherWorkspaceController::class, 'publications'])->name('researchers.publications');
        Route::get('/calls', [ResearcherWorkspaceController::class, 'calls'])->name('researchers.calls');
        Route::get('/projects/{project}/evaluations', [ResearcherWorkspaceController::class, 'evaluations'])->name('researchers.projects.evaluations');
    });

    Route::prefix('university')->middleware(['auth:sanctum', 'role.dashboard:universite', 'permission:university.view', 'throttle:api'])->group(function (): void {
        Route::get('/me', [UniversityWorkspaceController::class, 'me'])->name('university.me');
        Route::get('/students', [UniversityWorkspaceController::class, 'students'])->middleware('permission:university.students.view')->name('university.students');
        Route::get('/students/{student}', [UniversityWorkspaceController::class, 'student'])->middleware('permission:university.students.view')->name('university.students.show');
        Route::get('/applications', [UniversityWorkspaceController::class, 'applications'])->name('university.applications');
        Route::post('/applications/{application}/validate', [UniversityWorkspaceController::class, 'validateApplication'])->middleware('permission:university.applications.validate')->name('university.applications.validate');
        Route::post('/applications/{application}/request-correction', [UniversityWorkspaceController::class, 'requestCorrection'])->middleware('permission:university.applications.correction')->name('university.applications.correction');
        Route::post('/applications/{application}/reject', [UniversityWorkspaceController::class, 'rejectApplication'])->middleware('permission:university.applications.reject')->name('university.applications.reject');
        Route::post('/imports', [UniversityWorkspaceController::class, 'imports'])->middleware('permission:university.imports.create')->name('university.imports');
        Route::get('/statistics', [UniversityWorkspaceController::class, 'statistics'])->middleware('permission:university.reports.view')->name('university.statistics');
    });
});

Route::prefix('candidate')->name('api.candidate.')->middleware(['auth:sanctum', 'role.dashboard:etudiant', 'permission:applications.view', 'throttle:api'])->group(function (): void {
    Route::get('/candidatures', [CandidateApplicationController::class, 'index'])->name('applications.index');
    Route::post('/candidatures', [CandidateApplicationController::class, 'store'])->middleware('permission:applications.create')->name('applications.store');
    Route::get('/candidatures/{id}', [CandidateApplicationController::class, 'show'])->name('applications.show');
    Route::put('/candidatures/{id}', [CandidateApplicationController::class, 'update'])->middleware('permission:applications.update')->name('applications.update');
    Route::post('/candidatures/{id}/submit', [CandidateApplicationController::class, 'submit'])->middleware('permission:applications.submit')->name('applications.submit');
});

Route::prefix('evaluator')->name('api.evaluator.')->middleware(['auth:sanctum', 'role.dashboard:evaluateur', 'permission:evaluations.view', 'throttle:api'])->group(function (): void {
    Route::get('/assignments', [EvaluatorAssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/assignments/{id}', [EvaluatorAssignmentController::class, 'show'])->name('assignments.show');
    Route::post('/assignments/{id}/start', [EvaluatorAssignmentController::class, 'start'])->middleware('permission:evaluations.update')->name('assignments.start');
    Route::post('/assignments/{id}/evaluation', [EvaluatorAssignmentController::class, 'submit'])->middleware('permission:evaluations.update')->name('assignments.evaluation');
    Route::post('/assignments/{id}/conflict', [EvaluatorAssignmentController::class, 'conflict'])->middleware('permission:evaluations.update')->name('assignments.conflict');
});

Route::prefix('applications')->name('api.applications.workflow.')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('/{application}/submit', [ApplicationWorkflowApiController::class, 'submit'])->middleware(['role.dashboard:etudiant', 'permission:applications.submit'])->name('submit');
    Route::post('/{application}/verify', [ApplicationWorkflowApiController::class, 'verify'])->middleware(['permission:applications.verify'])->name('verify');
    Route::post('/{application}/assign-evaluators', [ApplicationWorkflowApiController::class, 'assign'])->middleware(['permission:applications.assign_evaluator'])->name('assign');
    Route::post('/{application}/commission', [ApplicationWorkflowApiController::class, 'commission'])->middleware(['permission:applications.review_commission'])->name('commission');
    Route::post('/{application}/decision', [ApplicationWorkflowApiController::class, 'decision'])->middleware(['permission:applications.decide'])->name('decision');
    Route::post('/{application}/publish-result', [ApplicationWorkflowApiController::class, 'publish'])->middleware(['permission:applications.publish_result'])->name('publish');
    Route::post('/{application}/award', [ApplicationWorkflowApiController::class, 'award'])->middleware(['permission:applications.award'])->name('award');
    Route::post('/{application}/commit', [ApplicationWorkflowApiController::class, 'commit'])->middleware(['permission:applications.finance'])->name('commit');
    Route::post('/{application}/disburse', [ApplicationWorkflowApiController::class, 'disburse'])->middleware(['permission:applications.disburse'])->name('disburse');
});
