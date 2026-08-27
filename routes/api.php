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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/statistics', DashboardStatisticsController::class)
    ->middleware(['auth:sanctum', 'permission:reports.view'])
    ->name('api.statistics');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:api')->name('auth.login');
    Route::get('/statistics/regions', RegionalStatisticsController::class)->middleware('throttle:api')->name('statistics.regions');
    Route::get('/search', GlobalSearchController::class)->middleware('throttle:api')->name('search');
    Route::get('/events', [PublicCatalogController::class, 'events'])->middleware('throttle:api')->name('events.index');
    Route::get('/partners', [PublicCatalogController::class, 'partners'])->middleware('throttle:api')->name('partners.index');
    Route::get('/testimonials', [PublicCatalogController::class, 'testimonials'])->middleware('throttle:api')->name('testimonials.index');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::delete('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/students', [StudentController::class, 'index'])->middleware('permission:users.view')->name('students.index');
        Route::get('/students/{id}', [StudentController::class, 'show'])->middleware('permission:users.view')->name('students.show');
        Route::get('/universities', [UniversityController::class, 'index'])->middleware('permission:university.view')->name('universities.index');
        Route::get('/universities/{id}', [UniversityController::class, 'show'])->middleware('permission:university.view')->name('universities.show');
        Route::get('/researchers', [ResearcherController::class, 'index'])->middleware('permission:research.view')->name('researchers.index');
        Route::get('/researchers/{id}', [ResearcherController::class, 'show'])->middleware('permission:research.view')->name('researchers.show');
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
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}', [NotificationController::class, 'show'])->name('notifications.show');
        Route::get('/claims', [ClaimController::class, 'index'])->middleware('permission:applications.view')->name('claims.index');
        Route::get('/claims/{id}', [ClaimController::class, 'show'])->middleware('permission:applications.view')->name('claims.show');
        Route::get('/statistics', StatisticsController::class)->middleware('permission:reports.view')->name('statistics');
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
