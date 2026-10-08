<?php

use App\Http\Controllers\PublicPortalController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\ResearcherPortalController;
use App\Http\Controllers\UniversityPortalController;
use App\Http\Controllers\CallPortalController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\AccountInvitationController;
use App\Http\Controllers\StudyLoanController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\EvaluatorPortalController;
use App\Http\Controllers\ApplicationWorkflowController;
use App\Http\Controllers\CommissionPortalController;
use App\Http\Controllers\IneController;
use App\Http\Controllers\FinancialReceiptController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

Route::get('/', [PublicPortalController::class, 'home'])->name('home');
Route::middleware('auth')->prefix('commissions')->name('commissions.')->group(function (): void {
    Route::get('/', [CommissionPortalController::class, 'index'])->name('index');
    Route::get('/{commission}', [CommissionPortalController::class, 'show'])->name('show');
    Route::post('/{commission}/votes', [CommissionPortalController::class, 'vote'])->middleware('throttle:30,1')->name('votes.store');
    Route::post('/{commission}/decisions', [CommissionPortalController::class, 'proposeDecision'])->middleware('throttle:20,1')->name('decisions.store');
    Route::post('/{commission}/minutes', [CommissionPortalController::class, 'saveMinutes'])->middleware('throttle:5,1')->name('minutes.store');
    Route::get('/{commission}/applications/{application}/documents/{document}', [CommissionPortalController::class, 'downloadApplicationDocument'])->name('documents.download');
    Route::get('/{commission}/minutes/download', [CommissionPortalController::class, 'downloadMinutes'])->name('minutes.download');
});
Route::get('/live', [\App\Http\Controllers\HealthController::class, 'live'])->name('health.live');
Route::get('/ready', [\App\Http\Controllers\HealthController::class, 'ready'])->name('health.ready');
Route::get('/finance/receipts/{receipt}/{token}', [FinancialReceiptController::class, 'show'])->middleware('throttle:20,1')->name('finance.receipts.show');
Route::get('/health', [\App\Http\Controllers\HealthController::class, 'health'])->name('health');
Route::get('/assistant', [\App\Http\Controllers\AssistantController::class, 'index'])->name('assistant.index');
Route::post('/assistant/ask', [\App\Http\Controllers\AssistantController::class, 'ask'])->middleware('throttle:10,1')->name('assistant.ask');
Route::post('/assistant/orientation', [\App\Http\Controllers\AssistantController::class, 'orientation'])->middleware('throttle:10,1')->name('assistant.orientation');
Route::post('/assistant/checklist', [\App\Http\Controllers\AssistantController::class, 'checklist'])->middleware('throttle:10,1')->name('assistant.checklist');
Route::post('/assistant/feedback', [\App\Http\Controllers\AssistantController::class, 'feedback'])->middleware('throttle:30,1')->name('assistant.feedback');
Route::get('/student/assistant', [\App\Http\Controllers\AssistantController::class, 'student'])->middleware(['auth', 'role.dashboard:etudiant'])->name('student.assistant');
Route::get('/ine', [IneController::class, 'index'])->name('ine.index');
Route::redirect('/recherche', '/ine/rechercher', 301)->name('ine.legacy.search');
Route::redirect('/recherche-ine', '/ine/rechercher', 301)->name('ine.legacy.search-ine');
Route::redirect('/recenser-ine', '/ine/declarer', 301)->name('ine.legacy.declare');
Route::redirect('/declaration-ine', '/ine/declarer', 301)->name('ine.legacy.declaration');
Route::get('/ine/declarer', [IneController::class, 'index'])->defaults('mode', 'declare')->name('ine.declare');
Route::post('/ine/declaration/verifier', [IneController::class, 'verifyDeclaration'])->middleware('throttle:ine-declaration')->name('ine.verify');
Route::post('/ine/declaration/confirmer', [IneController::class, 'confirmDeclaration'])->middleware(['auth', 'role.dashboard:etudiant', 'throttle:ine-declaration'])->name('ine.confirm');
Route::get('/ine/rechercher', [IneController::class, 'index'])->defaults('mode', 'recover')->name('ine.recover');
Route::post('/ine/rechercher', [IneController::class, 'search'])->middleware('throttle:ine-search')->name('ine.search');
Route::post('/ine/otp/envoyer', [IneController::class, 'sendOtp'])->middleware('throttle:ine-otp-send')->name('ine.otp.send');
Route::post('/ine/otp/verifier', [IneController::class, 'verifyOtp'])->middleware('throttle:ine-otp-verify')->name('ine.otp.verify');
Route::get('/language/{locale}', function (string $locale): \Illuminate\Http\RedirectResponse {
    abort_unless(in_array($locale, config('app.supported_locales'), true), 404);
    session(['locale' => $locale]);
    return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
})->name('language.switch');
Route::get('/sitemap.xml', [PublicPortalController::class, 'sitemap'])->name('sitemap');
Route::post('/newsletter', [PublicPortalController::class, 'newsletter'])->middleware('throttle:10,1')->name('newsletter.subscribe');
Route::post('/newsletter/unsubscribe', [PublicPortalController::class, 'newsletterUnsubscribe'])->middleware('throttle:10,1')->name('newsletter.unsubscribe');
Route::get('/newsletter/confirm/{token}', [PublicPortalController::class, 'newsletterConfirm'])->name('newsletter.confirm');
Route::get('/newsletter/unsubscribe/{token}', [PublicPortalController::class, 'newsletterUnsubscribeToken'])->name('newsletter.unsubscribe.token');
Route::post('/contact', [PublicPortalController::class, 'contact'])->middleware('throttle:10,1')->name('contact.submit');
Route::get('/contact', [PublicPortalController::class, 'contactPage'])->name('contact');
Route::get('/mediatheque', [PublicPortalController::class, 'media'])->name('media.index');
Route::get('/mediatheque/videos', [PublicPortalController::class, 'media'])->name('media.videos');
Route::get('/mediatheque/telechargements', [PublicPortalController::class, 'documents'])->name('media.downloads');
Route::get('/mediatheque/albums/{album}', [PublicPortalController::class, 'album'])->name('media.album');
Route::get('/centre-documentaire', [PublicPortalController::class, 'documents'])->name('documents.index');
Route::get('/centre-documentaire/{category}', [PublicPortalController::class, 'documents'])->name('documents.category');
Route::get('/documents/{document}/previsualiser', [PublicPortalController::class, 'previewDocument'])->name('documents.preview');
Route::get('/documents/{document}/telecharger', [PublicPortalController::class, 'downloadDocument'])->name('documents.download');
Route::get('/admin/documents/{document}/versions/{version}/download', \App\Http\Controllers\AdminDocumentVersionController::class)
    ->middleware('auth')
    ->name('admin.documents.versions.download');
Route::get('/faq', [PublicPortalController::class, 'faq'])->name('faq');
Route::get('/actualites/photos', [PublicPortalController::class, 'photos'])->name('news.photos');
Route::get('/actualites/videos', [PublicPortalController::class, 'videos'])->name('news.videos');
Route::get('/actualites/communiques', [PublicPortalController::class, 'pressReleases'])->name('news.press-releases');
Route::get('/actualites/communiques/{release:slug}', [PublicPortalController::class, 'pressRelease'])->name('news.press-release');
Route::prefix('evaluateur')->name('evaluator.')->middleware(['auth', 'role.dashboard:evaluateur', 'permission:evaluations.view'])->group(function (): void {
    Route::get('/', [EvaluatorPortalController::class, 'index'])->name('dashboard');
    Route::get('/candidatures/{application}', [EvaluatorPortalController::class, 'show'])->name('applications.show');
    Route::get('/candidatures/{application}/documents/{document}', [EvaluatorPortalController::class, 'downloadDocument'])->name('applications.documents.download');
    Route::post('/candidatures/{application}/evaluation', [EvaluatorPortalController::class, 'submit'])->middleware('throttle:10,1')->name('applications.evaluation');
    Route::post('/candidatures/{application}/conflit', [EvaluatorPortalController::class, 'conflict'])->middleware('throttle:10,1')->name('applications.conflict');
});
Route::get('/evaluator/dashboard', [EvaluatorPortalController::class, 'index'])
    ->middleware(['auth', 'role.dashboard:evaluateur', 'permission:evaluations.view'])
    ->name('evaluator.dashboard.alias');
Route::middleware(['auth', 'permission:evaluations.validate'])->group(function (): void {
    Route::post('/admin/applications/{application}/evaluators', [EvaluatorPortalController::class, 'assign'])->name('admin.applications.evaluators.assign');
    Route::put('/admin/applications/{application}/evaluators/{evaluation}', [EvaluatorPortalController::class, 'reassign'])->middleware('permission:applications.assign_evaluator')->name('admin.applications.evaluators.reassign');
    Route::delete('/admin/applications/{application}/evaluators/{evaluation}', [EvaluatorPortalController::class, 'removeAssignment'])->name('admin.applications.evaluators.remove');
    Route::post('/admin/evaluations/{evaluation}/validate', [EvaluatorPortalController::class, 'validateAssignment'])->middleware('permission:evaluations.validate')->name('admin.evaluations.validate');
    Route::post('/admin/applications/{application}/result', [EvaluatorPortalController::class, 'publishResult'])->name('admin.applications.result.publish');
});
Route::prefix('admin/applications')->name('admin.applications.workflow.')->middleware(['auth', 'permission:applications.update'])->group(function (): void {
    Route::post('/{application}/verify', [ApplicationWorkflowController::class, 'verify'])->middleware('permission:applications.verify')->name('verify');
    Route::post('/{application}/university-approve', [ApplicationWorkflowController::class, 'approveUniversity'])->middleware('permission:applications.verify')->name('university.approve');
    Route::post('/{application}/university-reject', [ApplicationWorkflowController::class, 'rejectUniversity'])->middleware('permission:applications.reject')->name('university.reject');
    Route::post('/{application}/assign-evaluators', [ApplicationWorkflowController::class, 'assign'])->middleware('permission:applications.assign_evaluator')->name('assign');
    Route::post('/{application}/commission', [ApplicationWorkflowController::class, 'commission'])->middleware('permission:applications.review_commission')->name('commission');
    Route::post('/{application}/decision', [ApplicationWorkflowController::class, 'decision'])->middleware('permission:applications.decide')->name('decision');
    Route::post('/{application}/publish-result', [ApplicationWorkflowController::class, 'publish'])->middleware('permission:applications.publish_result')->name('publish');
    Route::post('/{application}/award', [ApplicationWorkflowController::class, 'award'])->middleware('permission:applications.award')->name('award');
    Route::post('/{application}/commit', [ApplicationWorkflowController::class, 'commit'])->middleware('permission:applications.finance')->name('commit');
    Route::post('/{application}/disburse', [ApplicationWorkflowController::class, 'disburse'])->middleware('permission:applications.disburse')->name('disburse');
});
Route::get('/news', [PublicPortalController::class, 'news'])->name('news.index');
Route::get('/news/{article:slug}', [PublicPortalController::class, 'newsArticle'])->name('news.show');
Route::get('/researcher/register', [ResearcherPortalController::class, 'register'])->middleware('guest')->name('researcher.register');
Route::post('/researcher/register', [ResearcherPortalController::class, 'storeRegistration'])->middleware(['guest', 'throttle:6,1'])->name('researcher.register.store');
Route::get('/researcher/login', [ResearcherPortalController::class, 'login'])->middleware('guest')->name('researcher.login');
Route::post('/researcher/login', [ResearcherPortalController::class, 'authenticate'])->middleware(['guest', 'throttle:6,1'])->name('researcher.login.store');
Route::get('/researcher/pending', [ResearcherPortalController::class, 'pending'])->name('researcher.pending');
Route::get('/researcher/rejected', [ResearcherPortalController::class, 'rejected'])->name('researcher.rejected');
Route::get('/researcher/suspended', [ResearcherPortalController::class, 'suspended'])->name('researcher.suspended');
Route::get('/admin/applications/{application}/details', [\App\Http\Controllers\AdminApplicationController::class, 'show'])->middleware(['auth', 'permission:applications.assign_evaluator'])->name('admin.applications.show');
Route::middleware(['auth', 'permission:researchers.review'])->prefix('admin/researchers')->name('admin.researchers.')->group(function (): void {
    Route::post('/{researcher}/approve', fn (string $researcher) => ResearcherPortalController::approve($researcher, request()->user()))->name('approve');
    Route::post('/{researcher}/reject', [ResearcherPortalController::class, 'reject'])->name('reject');
    Route::post('/{researcher}/suspend', [ResearcherPortalController::class, 'suspend'])->name('suspend');
    Route::post('/{researcher}/reactivate', [ResearcherPortalController::class, 'reactivate'])->name('reactivate');
});
Route::get('/organisation', OrganizationController::class)->name('organization');
Route::get('/le-foser', [\App\Http\Controllers\InstitutionController::class, 'index'])->name('institution.index');
Route::get('/le-foser/{section}', [\App\Http\Controllers\InstitutionController::class, 'section'])->whereIn('section', ['historique', 'missions', 'vision', 'valeurs', 'organigramme', 'conseil-administration', 'direction-generale', 'directions', 'documents', 'rapports-annuels'])->name('institution.section');
Route::get('/le-foser/directions/{direction}', [\App\Http\Controllers\InstitutionController::class, 'direction'])->name('institution.direction');
Route::get('/le-foser/documents/{document}/telecharger', [\App\Http\Controllers\InstitutionController::class, 'download'])->name('institution.documents.download');
Route::get('/publications', [ResearcherPortalController::class, 'publicPublications'])->name('publications.public');
Route::get('/programmes', [\App\Http\Controllers\ProgramsController::class, 'index'])->name('programs.index');
Route::get('/programmes/{category}', [\App\Http\Controllers\ProgramsController::class, 'category'])->whereIn('category', ['aides-financieres', 'prets-etudes', 'recherche', 'innovation'])->name('programs.category');
Route::get('/programmes/{category}/{section}', [\App\Http\Controllers\ProgramsController::class, 'section'])->whereIn('category', ['aides-financieres', 'prets-etudes', 'recherche', 'innovation'])->name('programs.section');
Route::get('/programmes/recherche/projets-finances/{project}', [\App\Http\Controllers\ProgramsController::class, 'fundedProject'])->name('programs.project');
Route::get('/programmes/recherche/financement/{item}', [\App\Http\Controllers\ProgramsController::class, 'researchFundingDetail'])->name('programs.research-funding.detail');
Route::get('/programmes/innovation/{section}/{item}', [\App\Http\Controllers\ProgramsController::class, 'innovationDetail'])->whereIn('section', ['concours', 'incubation', 'valorisation'])->name('programs.innovation.detail');
Route::post('/programmes/prets-etudes/simulation', [\App\Http\Controllers\ProgramsController::class, 'simulate'])->name('programs.loan.simulate');
Route::get('/calls', [CallPortalController::class, 'index'])->name('calls.index');
Route::get('/calls/{call}', [CallPortalController::class, 'show'])->name('calls.show');
Route::get('/appels', [CallPortalController::class, 'index'])->name('appels.index');
Route::get('/appels/{call}', [CallPortalController::class, 'show'])->name('appels.show');
Route::get('/programmes/recherche/appels/{call}', [CallPortalController::class, 'show'])->name('programs.research-call.detail');
Route::get('/calls/{call}/results', [CallPortalController::class, 'results'])->name('calls.results');
Route::get('/calls/{call}/documents/{document}', [CallPortalController::class, 'downloadDocument'])->name('calls.documents.download');
Route::get('/agenda', [\App\Http\Controllers\EventPortalController::class, 'index'])->name('events.index');
Route::get('/agenda/{event:slug}', [\App\Http\Controllers\EventPortalController::class, 'show'])->name('events.show');
Route::get('/partenaires', [\App\Http\Controllers\PartnerPortalController::class, 'index'])->name('partners.index');
Route::get('/partenaires/{partner:slug}', [\App\Http\Controllers\PartnerPortalController::class, 'show'])->name('partners.show');
Route::get('/temoignages', [\App\Http\Controllers\TestimonialPortalController::class, 'index'])->name('testimonials.index');
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request): RedirectResponse {
    $request->fulfill();
    return redirect()->route('student.dashboard');
})->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');
Route::get('/forgot-password', [PasswordResetController::class, 'request'])->middleware('guest')->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware(['guest', 'throttle:6,1'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'form'])->middleware('guest')->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware(['guest', 'throttle:6,1'])->name('password.update');
Route::get('/student/email/verify', fn () => view('student.verify-email'))
    ->middleware('auth')
    ->name('verification.notice');
Route::get('/register', [StudentPortalController::class, 'register'])->middleware('guest')->name('register');
Route::post('/register', [StudentPortalController::class, 'storeRegistration'])->middleware(['guest', 'throttle:6,1'])->name('register.store');
Route::get('/invite/{token}', [AccountInvitationController::class, 'show'])->name('invitation.show');
Route::post('/invite/{token}', [AccountInvitationController::class, 'activate'])->middleware('throttle:10,1')->name('invitation.activate');
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [StudentPortalController::class, 'logout'])->name('auth.logout');
    Route::post('/student/logout', [StudentPortalController::class, 'logout'])->name('student.logout');
    Route::get('/notifications/history', [NotificationCenterController::class, 'history'])->name('notifications.history');
        Route::post('/notifications/{notification}/read', [NotificationCenterController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationCenterController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/notifications/preferences', [NotificationCenterController::class, 'preferences'])->name('notifications.preferences');
    Route::put('/notifications/preferences', [NotificationCenterController::class, 'updatePreference'])->name('notifications.preferences.update');
});
Route::get('/{page}', [PublicPortalController::class, 'page'])
    ->whereIn('page', ['about', 'programs', 'calls', 'news', 'media', 'documents', 'faq', 'contact'])
    ->name('public.page');

Route::prefix('student')->name('student.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/register', [StudentPortalController::class, 'register'])->name('register');
        Route::post('/register', [StudentPortalController::class, 'storeRegistration'])->middleware('throttle:6,1')->name('register.store');
        Route::get('/login', [StudentPortalController::class, 'login'])->name('login');
        Route::post('/login', [StudentPortalController::class, 'authenticate'])->middleware('throttle:6,1')->name('login.store');
    });

    Route::middleware(['auth', 'role.dashboard:etudiant'])->group(function (): void {
        Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request): RedirectResponse {
            $request->fulfill();
            return redirect()->route('student.dashboard');
        })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
        Route::post('/email/verification-notification', function (Request $request): RedirectResponse {
            try {
                $request->user()->sendEmailVerificationNotification();
            } catch (TransportExceptionInterface) {
                return back()->withErrors(['email' => 'Le service email est temporairement indisponible. Vérifiez la configuration SMTP Gmail.']);
            }

            return back()->with('status', 'Lien de vérification envoyé.');
        })->middleware('throttle:6,1')->name('verification.send');
    });

    Route::middleware(['auth', 'role.dashboard:etudiant'])->group(function (): void {
        Route::get('/', [StudentPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/programs', [StudentPortalController::class, 'programs'])->name('programs');
        Route::get('/programs/{program}/eligibility', [StudentPortalController::class, 'eligibility'])->name('programs.eligibility');
        Route::post('/programs/{program}/eligibility', [StudentPortalController::class, 'evaluateEligibility'])->middleware('throttle:10,1')->name('programs.eligibility.evaluate');
        Route::get('/applications/create/{program}', [StudentPortalController::class, 'createApplicationForm'])->name('applications.create-form');
        Route::get('/results', [StudentPortalController::class, 'results'])->name('results');
        Route::get('/attestations/{application}', [StudentPortalController::class, 'attestation'])->name('attestations.show');
        Route::get('/notifications', [StudentPortalController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read-all', [StudentPortalController::class, 'markAllNotificationsAsRead'])->name('notifications.read-all');
        Route::get('/claims', [StudentPortalController::class, 'claims'])->name('claims');
        Route::get('/profile', [StudentPortalController::class, 'profile'])->name('profile');
        Route::put('/profile', [StudentPortalController::class, 'updateProfile'])->name('profile.update');
        Route::get('/applications', [StudentPortalController::class, 'applications'])->name('applications');
        Route::post('/applications', [StudentPortalController::class, 'createApplication'])->name('applications.create');
        Route::get('/applications/{application}/edit', [StudentPortalController::class, 'editApplication'])->name('applications.edit');
        Route::put('/applications/{application}', [StudentPortalController::class, 'updateApplication'])->name('applications.update');
        Route::delete('/applications/{application}', [StudentPortalController::class, 'deleteApplication'])->name('applications.delete');
        Route::post('/applications/{application}/submit', [StudentPortalController::class, 'submitApplication'])->name('applications.submit');
        Route::get('/documents', [StudentPortalController::class, 'documents'])->name('documents');
        Route::get('/documents/{document}/download', [StudentPortalController::class, 'downloadDocument'])->name('documents.download');
        Route::put('/documents/{document}', [StudentPortalController::class, 'replaceDocument'])->name('documents.replace');
        Route::delete('/documents/{document}', [StudentPortalController::class, 'deleteDocument'])->name('documents.delete');
        Route::get('/payments', [StudentPortalController::class, 'payments'])->name('payments');
        Route::get('/study-loans', [StudyLoanController::class, 'index'])->name('study-loans');
        Route::post('/study-loans/simulate', [StudyLoanController::class, 'simulate'])->name('study-loans.simulate');
        Route::post('/study-loans/apply', [StudyLoanController::class, 'apply'])->name('study-loans.apply');
        Route::post('/documents', [StudentPortalController::class, 'uploadDocument'])->name('documents.upload');
        Route::get('/support', [StudentPortalController::class, 'support'])->name('support');
        Route::post('/claims', [StudentPortalController::class, 'createClaim'])->name('claims.create');
        Route::post('/messages', [StudentPortalController::class, 'createThread'])->name('messages.create');
        Route::post('/messages/{thread}/reply', [StudentPortalController::class, 'replyMessage'])->name('messages.reply');
    });
});

Route::prefix('researcher')->name('researcher.')->middleware(['auth', 'role.dashboard:chercheur,researcher', 'permission:research.view'])->group(function (): void {
    Route::get('/', [ResearcherPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/assistant', [\App\Http\Controllers\AssistantController::class, 'researcher'])->name('assistant');
    Route::get('/calls', [ResearcherPortalController::class, 'calls'])->name('calls');
    Route::get('/profile', [ResearcherPortalController::class, 'profile'])->name('profile');
    Route::put('/profile', [ResearcherPortalController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/cv', [ResearcherPortalController::class, 'uploadCv'])->name('profile.cv');
    Route::get('/workspace/{section}', [ResearcherPortalController::class, 'workspaceSection'])->whereIn('section', ['projects', 'applications', 'evaluations', 'finance', 'documents', 'notifications', 'calendar'])->name('workspace.section');
    Route::get('/projects/create', [ResearcherPortalController::class, 'createProjectForm'])->middleware('permission:research.manage')->name('projects.create-form');
    Route::get('/projects/{project}', [ResearcherPortalController::class, 'showProject'])->name('projects.show');
    Route::get('/projects/{project}/tracking', [ResearcherPortalController::class, 'tracking'])->name('projects.tracking');
    Route::get('/projects/{project}/documents/{document}', [ResearcherPortalController::class, 'downloadProjectDocument'])->name('projects.documents.download');
    Route::post('/projects', [ResearcherPortalController::class, 'createProject'])->middleware('permission:research.manage')->name('projects.create');
    Route::put('/projects/{project}', [ResearcherPortalController::class, 'updateProject'])->middleware('permission:research.manage')->name('projects.update');
    Route::post('/projects/{project}/submit', [ResearcherPortalController::class, 'submitProject'])->middleware('permission:research.manage')->name('projects.submit');
    Route::post('/projects/{project}/members', [ResearcherPortalController::class, 'addMember'])->middleware('permission:research.manage')->name('projects.members');
    Route::post('/projects/{project}/documents', [ResearcherPortalController::class, 'uploadProjectDocument'])->middleware('permission:research.manage')->name('projects.documents');
    Route::post('/projects/{project}/publications', [ResearcherPortalController::class, 'createPublication'])->middleware('permission:research.manage')->name('projects.publications.create');
    Route::put('/publications/{publication}', [ResearcherPortalController::class, 'updatePublication'])->middleware('permission:research.manage')->name('publications.update');
    Route::get('/publications', [ResearcherPortalController::class, 'publications'])->name('publications');
    Route::get('/innovation', [ResearcherPortalController::class, 'innovation'])->name('innovation');
    Route::get('/conventions', [ResearcherPortalController::class, 'conventions'])->name('conventions');
    Route::get('/disbursements', [ResearcherPortalController::class, 'disbursements'])->name('disbursements');
    Route::get('/laboratory', [ResearcherPortalController::class, 'laboratory'])->name('laboratory');
});

Route::prefix('university')->name('university.')->middleware(['auth', 'university.access', 'permission:university.view'])->group(function (): void {
    Route::get('/', [UniversityPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/assistant', [\App\Http\Controllers\AssistantController::class, 'university'])->name('assistant');
    Route::get('/profile', [UniversityPortalController::class, 'profile'])->name('profile');
    Route::put('/profile', [UniversityPortalController::class, 'updateProfile'])->middleware('permission:university.manage')->name('profile.update');
    Route::get('/users', [UniversityPortalController::class, 'users'])->middleware('permission:university.manage')->name('users');
    Route::post('/users', [UniversityPortalController::class, 'addUser'])->middleware('permission:university.manage')->name('users.add');
    Route::delete('/users/{user}', [UniversityPortalController::class, 'removeUser'])->middleware('permission:university.manage')->name('users.remove');
    Route::get('/students', [UniversityPortalController::class, 'students'])->middleware('permission:university.students.view')->name('students');
    Route::get('/students/{student}', [UniversityPortalController::class, 'student'])->middleware('permission:university.students.view')->name('students.show');
    Route::get('/laboratories', [UniversityPortalController::class, 'laboratories'])->name('laboratories');
    Route::post('/laboratories', [UniversityPortalController::class, 'addLaboratory'])->middleware('permission:university.manage')->name('laboratories.store');
    Route::post('/students/{student}/validate', [UniversityPortalController::class, 'validateStudent'])->middleware('permission:university.students.validate')->name('students.validate');
    Route::get('/applications', [UniversityPortalController::class, 'applications'])->middleware('permission:university.applications.validate')->name('applications');
    Route::get('/applications/{application}', [UniversityPortalController::class, 'applicationDetail'])->middleware('permission:university.applications.validate')->name('applications.show');
    Route::get('/applications/{application}/documents/{document}', [UniversityPortalController::class, 'downloadApplicationDocument'])->middleware('permission:university.applications.validate')->name('applications.documents.download');
    Route::post('/applications/{application}/validate', [UniversityPortalController::class, 'validateApplication'])->middleware('permission:university.applications.validate')->name('applications.validate');
    Route::post('/applications/{application}/reject', [UniversityPortalController::class, 'rejectApplication'])->middleware('permission:university.applications.reject')->name('applications.reject');
    Route::post('/applications/{application}/correction', [UniversityPortalController::class, 'requestApplicationCorrection'])->middleware('permission:university.applications.correction')->name('applications.correction');
    Route::post('/imports/students', [UniversityPortalController::class, 'importStudents'])->middleware('permission:university.imports.create')->name('imports.students');
    Route::post('/imports/students/preview', [UniversityPortalController::class, 'previewStudentImport'])->middleware('permission:university.imports.create')->name('imports.students.preview');
    Route::get('/imports', [UniversityPortalController::class, 'imports'])->middleware('permission:university.imports.create')->name('imports');
    Route::get('/imports/template', [UniversityPortalController::class, 'importTemplate'])->middleware('permission:university.imports.create')->name('imports.template');
    Route::get('/reports', [UniversityPortalController::class, 'reports'])->middleware('permission:university.reports.view')->name('reports');
    Route::get('/reports/export', [UniversityPortalController::class, 'exportReports'])->middleware('permission:university.reports.view')->name('reports.export');
    Route::post('/messages', [UniversityPortalController::class, 'messageFoser'])->middleware('permission:university.messages.create')->name('messages');
    Route::get('/messages', [UniversityPortalController::class, 'messages'])->middleware('permission:university.messages.create')->name('messages.index');
    Route::post('/messages/{thread}/reply', [UniversityPortalController::class, 'replyMessage'])->middleware('permission:university.messages.create')->name('messages.reply');
});

foreach ([
    'partenaire' => ['path' => 'partner', 'permission' => 'calls.view'],
    'agent_dossier' => ['path' => 'agent', 'permission' => 'applications.view'],
    'agent_finance' => ['path' => 'finance', 'permission' => 'finance.view'],
    'agent_recherche' => ['path' => 'research', 'permission' => 'research.view'],
    'agent_communication' => ['path' => 'communication', 'permission' => 'content.view'],
    'gestionnaire' => ['path' => 'manager', 'permission' => 'applications.view'],
] as $role => $dashboard) {
    Route::get('/'.$dashboard['path'].'/dashboard', [\App\Http\Controllers\RoleDashboardController::class, 'show'])
        ->middleware(['auth', 'role.dashboard:'.$role, 'permission:'.$dashboard['permission']])
        ->name($role.'.dashboard')->defaults('role', $role);
}

Route::prefix('director')->name('directeur_general.')->middleware(['auth', 'role.dashboard:directeur_general', 'permission:reports.view'])->group(function (): void {
    Route::get('/dashboard', [\App\Http\Controllers\DirectorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/export.xlsx', [\App\Http\Controllers\DirectorDashboardController::class, 'exportExcel'])->middleware('permission:reports.export')->name('dashboard.export.xlsx');
    Route::get('/dashboard/export.pdf', [\App\Http\Controllers\DirectorDashboardController::class, 'exportPdf'])->middleware('permission:reports.export')->name('dashboard.export.pdf');
});
