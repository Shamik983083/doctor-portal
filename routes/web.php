<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Clinician\DashboardController as ClinicianDashboard;
use App\Http\Controllers\Web\Clinician\CaseController as ClinicianCaseController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Web\Admin\CaseController as AdminCaseController;
use App\Http\Controllers\Web\Admin\PatientController as AdminPatientController;
use App\Http\Controllers\Web\Admin\PartnerController as AdminPartnerController;
use App\Http\Controllers\Web\Admin\PartnerProductPlanController as AdminPartnerProductPlanController;
use App\Http\Controllers\Web\Admin\ClinicianController as AdminClinicianController;
use App\Http\Controllers\Web\Admin\OfferingController as AdminOfferingController;
use App\Http\Controllers\Web\Admin\OfferingCategoryController as AdminOfferingCategoryController;
use App\Http\Controllers\Web\Admin\QuestionnaireController as AdminQuestionnaireController;
use App\Http\Controllers\Web\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Web\Admin\WebhookDeliveryController as AdminWebhookDeliveryController;
use App\Http\Controllers\Web\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Web\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Web\Admin\TriageRuleController as AdminTriageRuleController;
use App\Http\Controllers\Web\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Web\Clinician\NotificationController as ClinicianNotificationController;
use App\Http\Controllers\Web\Partner\NotificationController as PartnerNotificationController;
use App\Http\Controllers\Web\Form\QuestionnaireFormController;
use App\Http\Controllers\Web\Partner\DashboardController as PartnerDashboard;
use App\Http\Controllers\Web\Partner\OfferingController as PartnerOfferingController;
use App\Http\Controllers\Web\Partner\PatientController as PartnerPatientController;
use App\Http\Controllers\Web\Partner\CaseController as PartnerCaseController;
use App\Http\Controllers\Web\Partner\CredentialController as PartnerCredentialController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Password reset
Route::get('/forgot-password', [ForgotPasswordController::class, 'showForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::get('/', fn() => redirect('/login'));

// Public questionnaire form renderer · no auth required
Route::prefix('forms')->name('forms.')->group(function () {
    Route::get('/{uuid}',  [QuestionnaireFormController::class, 'show'])->name('show');
    Route::post('/{uuid}', [QuestionnaireFormController::class, 'submit'])->name('submit');
});

// /ma-portal was a read-only showcase retired in favour of the real portals.
// 301s keep stale bookmarks working until the next release.
Route::middleware(['auth'])->group(function () {
    Route::permanentRedirect('/ma-portal', '/admin/dashboard');
    Route::permanentRedirect('/ma-portal/practitioner', '/clinician/dashboard');
    Route::permanentRedirect('/ma-portal/admin', '/admin/dashboard');
    Route::permanentRedirect('/ma-portal/super-admin', '/admin/dashboard');
});

// Clinician Portal
/*
 * `clinician.portal` guarantees a Clinician record exists behind the user, so
 * the controllers' `Auth::user()->clinician` cannot be null. The role gate still
 * says who may knock; this says who has a queue to show.
 */
Route::prefix('clinician')->middleware(['auth', 'role:clinician|admin', 'clinician.portal'])->name('clinician.')->group(function () {
    Route::get('/dashboard', [ClinicianDashboard::class, 'index'])->name('dashboard');

    /*
     * The provider pool (Devin msg 2308). A doctor asks for a number of cases and
     * the pool decides what they get. They never see the queue, which is why
     * there is no index of available cases here, only a request form and their
     * own history.
     */
    Route::get('/pool',  [\App\Http\Controllers\Web\Clinician\PoolController::class, 'index'])->name('pool.index');
    Route::post('/pool', [\App\Http\Controllers\Web\Clinician\PoolController::class, 'store'])->name('pool.request');

    Route::prefix('cases')->name('cases.')->group(function () {
        Route::get('/queue', [ClinicianCaseController::class, 'queue'])->name('queue');
        Route::get('/my-cases', [ClinicianCaseController::class, 'myCases'])->name('my-cases');
        // Refills (Devin msg 2285): the same grid, filtered to check-ins from
        // patients this clinician has seen before. Must sit before /{uuid} so
        // "refills" is not read as a case uuid.
        Route::get('/refills', [ClinicianCaseController::class, 'refills'])->name('refills');
        Route::get('/{uuid}', [ClinicianCaseController::class, 'show'])->name('show');
        Route::post('/{uuid}/assign', [ClinicianCaseController::class, 'assign'])->name('assign');
        Route::get('/{uuid}/prescribe', [ClinicianCaseController::class, 'prescribeForm'])->name('prescribe.form');
        Route::post('/{uuid}/prescribe', [ClinicianCaseController::class, 'prescribe'])->name('prescribe');
        // C12: review-before-send — must be before /{uuid}/approve so 'review' is not parsed as a case uuid
        Route::get('/{uuid}/prescribe/review', [ClinicianCaseController::class, 'prescribeReview'])->name('prescribe.review');
        Route::post('/{uuid}/prescribe/confirm', [ClinicianCaseController::class, 'prescribeConfirm'])->name('prescribe.confirm');
        Route::get('/{uuid}/prescribe/discard', [ClinicianCaseController::class, 'prescribeDiscard'])->name('prescribe.discard');
        Route::post('/{uuid}/approve', [ClinicianCaseController::class, 'approve'])->name('approve');
        // Returns a draft for the provider to edit. Persists nothing, sends nothing.
        Route::post('/{uuid}/draft-note', [ClinicianCaseController::class, 'draftNote'])->name('draft-note');
        Route::post('/{uuid}/draft-rejection', [ClinicianCaseController::class, 'draftRejection'])->name('draft-rejection');
        Route::post('/{uuid}/cancel', [ClinicianCaseController::class, 'cancel'])->name('cancel');
        Route::post('/{uuid}/support', [ClinicianCaseController::class, 'escalateToSupport'])->name('support');
        Route::post('/{uuid}/notes', [ClinicianCaseController::class, 'addNote'])->name('notes.store');
        Route::post('/{uuid}/messages', [ClinicianCaseController::class, 'sendMessage'])->name('messages.store');
        Route::get('/{uuid}/messages/poll', [ClinicianCaseController::class, 'pollMessages'])->name('messages.poll');
        Route::post('/{uuid}/files', [ClinicianCaseController::class, 'uploadFile'])->name('files.store');
        Route::get('/{uuid}/files/{fileUuid}/download', [ClinicianCaseController::class, 'downloadFile'])->name('files.download');
        Route::get('/{uuid}/files/{fileUuid}/preview', [ClinicianCaseController::class, 'previewFile'])->name('files.preview');
        Route::delete('/{uuid}/files/{fileUuid}', [ClinicianCaseController::class, 'deleteFile'])->name('files.destroy');
        Route::get('/{uuid}/prescription-document/{documentUuid}', [ClinicianCaseController::class, 'downloadPrescriptionDocument'])->name('prescription-document.download');
        Route::post('/batch/preflight', [ClinicianCaseController::class, 'batchPreflight'])->name('batch.preflight');
        Route::post('/batch/submit',    [ClinicianCaseController::class, 'batchSubmit'])->name('batch.submit');
    });

    Route::get('/queue', [ClinicianCaseController::class, 'queue'])->name('queue');

    // Messages For Provider (Devin msg 2256): a dedicated inbox screen, matching
    // the design preview. Lives OUTSIDE the cases/{uuid} group so "messages" is
    // never captured as a case uuid.
    Route::get('/messages', [ClinicianCaseController::class, 'messagesInbox'])->name('messages.index');

    Route::get('/notifications', [ClinicianNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [ClinicianNotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [ClinicianNotificationController::class, 'markAllRead'])->name('notifications.read-all');
});

// Admin Console
Route::prefix('admin')->middleware(['auth', 'role:admin|super_admin'])->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    // Notifications
    Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [AdminNotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [AdminNotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Patients
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('/', [AdminPatientController::class, 'index'])->name('index');
        Route::get('/{id}', [AdminPatientController::class, 'show'])->name('show');
        Route::patch('/{id}/collaborating-clinician', [AdminPatientController::class, 'updateCollaboratingClinician'])->name('collaborating-clinician.update');
        Route::delete('/{id}', [AdminPatientController::class, 'destroy'])->name('destroy');
    });

    // Cases
    Route::prefix('cases')->name('cases.')->group(function () {
        Route::get('/', [AdminCaseController::class, 'index'])->name('index');
        Route::get('/{uuid}', [AdminCaseController::class, 'show'])->name('show');
        Route::post('/{uuid}/assign', [AdminCaseController::class, 'assign'])->name('assign');
        Route::post('/{uuid}/files', [AdminCaseController::class, 'uploadFile'])->name('files.store');
        Route::get('/{uuid}/files/{fileUuid}/download', [AdminCaseController::class, 'downloadFile'])->name('files.download');
        Route::get('/{uuid}/files/{fileUuid}/preview', [AdminCaseController::class, 'previewFile'])->name('files.preview');
        Route::delete('/{uuid}/files/{fileUuid}', [AdminCaseController::class, 'deleteFile'])->name('files.destroy');
        Route::delete('/{uuid}', [AdminCaseController::class, 'destroy'])->name('destroy');
    });

    // Partners (Super Admin only). Storefronts carry their own Healthie
    // credentials, so this is an integration surface, not an operational one.
    // Devin msg 2117: "All API integrations etc should be a super admin function."
    Route::prefix('partners')->name('partners.')->middleware('role:super_admin')->group(function () {
        Route::get('/', [AdminPartnerController::class, 'index'])->name('index');
        Route::get('/create', [AdminPartnerController::class, 'create'])->name('create');
        Route::post('/', [AdminPartnerController::class, 'store'])->name('store');
        Route::get('/{id}', [AdminPartnerController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [AdminPartnerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AdminPartnerController::class, 'update'])->name('update');
        Route::get('/{id}/users/create', [AdminPartnerController::class, 'createUser'])->name('users.create');
        Route::post('/{id}/users', [AdminPartnerController::class, 'storeUser'])->name('users.store');
        Route::post('/{id}/regenerate-credentials', [AdminPartnerController::class, 'regenerateCredentials'])->name('regenerate-credentials');
        Route::post('/{id}/webhooks', [AdminPartnerController::class, 'storeWebhook'])->name('webhooks.store');
        Route::patch('/{id}/webhooks/{webhookId}', [AdminPartnerController::class, 'updateWebhook'])->name('webhooks.update');
        Route::delete('/{id}/webhooks/{webhookId}', [AdminPartnerController::class, 'destroyWebhook'])->name('webhooks.destroy');
        Route::delete('/{id}', [AdminPartnerController::class, 'destroy'])->name('destroy');
        // Product plans (one-to-many product_key ↔ offering mapping)
        Route::get('/{id}/product-plans', [AdminPartnerProductPlanController::class, 'index'])->name('product-plans.index');
        Route::post('/{id}/product-plans', [AdminPartnerProductPlanController::class, 'store'])->name('product-plans.store');
        Route::delete('/{id}/product-plans/{planId}', [AdminPartnerProductPlanController::class, 'destroy'])->name('product-plans.destroy');
    });

    // Clinicians
    Route::prefix('clinicians')->name('clinicians.')->group(function () {
        Route::get('/', [AdminClinicianController::class, 'index'])->name('index');
        Route::get('/create', [AdminClinicianController::class, 'create'])->name('create');
        Route::post('/', [AdminClinicianController::class, 'store'])->name('store');
        // B4: Doctor Admin bulk case reassignment by provider
        Route::get('/bulk-reassign',  [AdminClinicianController::class, 'bulkReassign'])->name('bulk-reassign');
        Route::post('/bulk-reassign', [AdminClinicianController::class, 'bulkReassignSubmit'])->name('bulk-reassign.submit');
        // Priority management · must be before /{id} wildcard
        Route::get('/priority', [AdminClinicianController::class, 'priorityIndex'])->name('priority');
        Route::patch('/reorder', [AdminClinicianController::class, 'reorder'])->name('reorder');
        Route::patch('/{id}/case-load', [AdminClinicianController::class, 'updateCaseLoad'])->name('case-load');
        Route::get('/{id}', [AdminClinicianController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [AdminClinicianController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AdminClinicianController::class, 'update'])->name('update');
        Route::delete('/{id}', [AdminClinicianController::class, 'destroy'])->name('destroy');
    });

    // Questions (individual question library)
    Route::prefix('questions')->name('questions.')->group(function () {
        Route::get('/', [AdminQuestionController::class, 'index'])->name('index');
        Route::get('/{id}', [AdminQuestionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [AdminQuestionController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AdminQuestionController::class, 'update'])->name('update');
        Route::delete('/{id}', [AdminQuestionController::class, 'destroy'])->name('destroy');
        Route::post('/bulk-delete', [AdminQuestionController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::patch('/{id}/toggle-status', [AdminQuestionController::class, 'toggleStatus'])->name('toggle-status');
    });

    // Questionnaires
    // READ open to both admin tiers; WRITE restricted to super_admin (Doctor Admin is view-only).
    // GET /create must be registered before GET /{id} so the literal "create" is not captured as an id.
    Route::prefix('questionnaires')->name('questionnaires.')->group(function () {
        Route::get('/', [AdminQuestionnaireController::class, 'index'])->name('index');
        Route::get('/create', [AdminQuestionnaireController::class, 'create'])
            ->middleware('role:super_admin')->name('create');
        Route::get('/{id}', [AdminQuestionnaireController::class, 'show'])->name('show');

        Route::middleware('role:super_admin')->group(function () {
            Route::post('/', [AdminQuestionnaireController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [AdminQuestionnaireController::class, 'edit'])->name('edit');
            Route::put('/{id}', [AdminQuestionnaireController::class, 'update'])->name('update');
            Route::delete('/{id}', [AdminQuestionnaireController::class, 'destroy'])->name('destroy');
        });
    });

    // Offerings
    /*
     * The catalog. READ is open to both admin tiers, WRITE is super admin only.
     *
     * This mirrors RolesAndPermissionsSeeder exactly, which grants `admin` the
     * single permission `view offerings` and withholds create, update and
     * delete. Before this, every write here was reachable by any admin, so the
     * seeder described a restriction that no route enforced.
     *
     * A Doctor Admin still needs to SEE what is prescribable to run their
     * doctors, which is why index and show stay open rather than gating the
     * whole prefix.
     */
    Route::prefix('offerings')->name('offerings.')->group(function () {
        Route::get('/', [AdminOfferingController::class, 'index'])->name('index');

        /* ORDER MATTERS. GET /create must be registered BEFORE GET /{id} or the
           literal "create" is captured as an id and the create page renders as a
           lookup for an offering that does not exist. The other write routes use
           distinct verbs, so only this one has to sit up here. */
        Route::get('/create', [AdminOfferingController::class, 'create'])
            ->middleware('role:super_admin')->name('create');

        Route::get('/{id}', [AdminOfferingController::class, 'show'])->name('show');

        Route::middleware('role:super_admin')->group(function () {
            Route::post('/', [AdminOfferingController::class, 'store'])->name('store');
            Route::put('/{id}', [AdminOfferingController::class, 'update'])->name('update');
            Route::delete('/{id}', [AdminOfferingController::class, 'destroy'])->name('destroy');
            Route::patch('/{id}/toggle-status', [AdminOfferingController::class, 'toggleStatus'])->name('toggle-status');
            Route::post('/{id}/approve', [AdminOfferingController::class, 'approve'])->name('approve');
            Route::post('/{id}/reject',  [AdminOfferingController::class, 'reject'])->name('reject');
            Route::post('/{id}/questionnaires',          [AdminOfferingController::class, 'attachQuestionnaire'])->name('questionnaires.attach');
            Route::delete('/{id}/questionnaires/{qId}',  [AdminOfferingController::class, 'detachQuestionnaire'])->name('questionnaires.detach');
        });
    });

    /*
     * INTEGRATION AND PLATFORM CONFIGURATION: SUPER ADMIN ONLY (Devin msg 2117).
     *
     * Everything in this group either reaches outside MEDAXIS or changes how the
     * platform behaves for every doctor: the API guides, webhook deliveries, SLA
     * settings and the triage rule set. A Doctor Admin runs their doctors; they
     * do not configure the platform or its outbound connections.
     *
     * Grouped rather than annotated route by route so a new integration added
     * here inherits the restriction instead of relying on someone remembering it.
     */
    Route::middleware('role:super_admin')->group(function () {

    // Developer Guide
    Route::get('/guide/messaging', fn() => view('admin.guide.messaging'))->name('guide.messaging');
    Route::get('/guide/webhooks', fn() => view('admin.guide.webhooks'))->name('guide.webhooks');
    Route::get('/guide/glp-api', function () {
        $questionnaire = \App\Models\Questionnaire::with([
            'questions' => fn($q) => $q->where('is_active', true)->orderBy('step_number')->orderBy('sort_order'),
        ])->where('name', 'GLP Questionnaire')->first();
        return view('admin.guide.weightloss-api', compact('questionnaire'));
    })->name('guide.glp-api');
    // Legacy redirect so old bookmarks still work
    Route::redirect('/guide/weightloss-api', '/admin/guide/glp-api', 301);

    Route::get('/guide/antiaging-api', function () {
        $questionnaire = \App\Models\Questionnaire::with([
            'questions' => fn($q) => $q->where('is_active', true)->orderBy('step_number')->orderBy('sort_order'),
        ])->where('name', 'Anti-Aging')->first();
        return view('admin.guide.antiaging-api', compact('questionnaire'));
    })->name('guide.antiaging-api');

    // Webhook Deliveries
    Route::prefix('webhooks')->name('webhooks.')->group(function () {
        Route::get('/',              [AdminWebhookDeliveryController::class, 'index'])->name('index');
        Route::post('/{uuid}/resend',[AdminWebhookDeliveryController::class, 'resend'])->name('resend');
    });

    // Case SLA Targets
    Route::get('/settings',  [AdminSettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');

    // Audit Log — read-only record of all admin-initiated model changes
    Route::get('/audit-log', [AdminAuditLogController::class, 'index'])->name('audit-log.index');

    // Triage Rule Set — per-questionnaire disqualifier rules
    Route::prefix('triage-rules')->name('triage-rules.')->group(function () {
        Route::get('/', [AdminTriageRuleController::class, 'index'])->name('index');
        Route::post('/option/{questionnaireQuestion}',         [AdminTriageRuleController::class, 'storeOption'])->name('option.store');
        Route::patch('/option/{questionnaireQuestion}/toggle', [AdminTriageRuleController::class, 'toggleOption'])->name('option.toggle');
        Route::put('/option/{questionnaireQuestion}',          [AdminTriageRuleController::class, 'updateOption'])->name('option.update');
        Route::delete('/option/{questionnaireQuestion}',       [AdminTriageRuleController::class, 'destroyOption'])->name('option.destroy');
    });

    // Case routing policy. Decides which doctor sees which patient, so it sits
    // with the other super-admin configuration.
    Route::prefix('routing')->name('routing.')->group(function () {
        Route::get('/',  [\App\Http\Controllers\Web\Admin\RoutingPolicyController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Web\Admin\RoutingPolicyController::class, 'store'])->name('store');
        Route::post('/{id}/activate', [\App\Http\Controllers\Web\Admin\RoutingPolicyController::class, 'activate'])->name('activate');

        /*
         * The state synchronous-visit matrix. Super admin only, with the rest of
         * this group, because it encodes telehealth law rather than day-to-day
         * operations (Devin msg 2313 Q4: "we need to adjust as super admin as
         * laws change frequently").
         */
        Route::get('/visit-requirements',  [\App\Http\Controllers\Web\Admin\RoutingOperationsController::class, 'visitRequirements'])->name('visit-requirements');
        Route::post('/visit-requirements', [\App\Http\Controllers\Web\Admin\RoutingOperationsController::class, 'storeVisitRequirement'])->name('visit-requirements.store');
        Route::delete('/visit-requirements/{id}', [\App\Http\Controllers\Web\Admin\RoutingOperationsController::class, 'destroyVisitRequirement'])->name('visit-requirements.destroy');
    });

    }); // end super-admin-only integration and configuration group

    /*
     * ROUTING OPERATIONS, open to Doctor Admins as well as super admins.
     *
     * Deliberately OUTSIDE the super-admin group above. Devin msg 2308 named the
     * Doctor Admin first for exception visibility ("WE NEED THE DOCTOR ADMIN AND
     * SUPER ADMIN TO SEE CASES THAT AREN'T ASSIGNED"), and msg 2313 Q6 put SLA
     * ownership and pull approvals in their hands. A screen only a super admin
     * can open cannot do either job.
     *
     * Each action scopes to the doctors the admin is over, via
     * Clinician::visibleTo(), so widening the route does not widen the data.
     */
    Route::prefix('routing')->name('routing.')->group(function () {
        Route::get('/exceptions', [\App\Http\Controllers\Web\Admin\RoutingOperationsController::class, 'exceptions'])->name('exceptions');
        Route::post('/exceptions/{id}/resolve', [\App\Http\Controllers\Web\Admin\RoutingOperationsController::class, 'resolveException'])->name('exceptions.resolve');

        Route::get('/pull-requests', [\App\Http\Controllers\Web\Admin\RoutingOperationsController::class, 'pullRequests'])->name('pull-requests');
        Route::post('/pull-requests/{id}/approve', [\App\Http\Controllers\Web\Admin\RoutingOperationsController::class, 'approvePull'])->name('pull-requests.approve');
        Route::post('/pull-requests/{id}/deny',    [\App\Http\Controllers\Web\Admin\RoutingOperationsController::class, 'denyPull'])->name('pull-requests.deny');

        Route::get('/sla',  [\App\Http\Controllers\Web\Admin\RoutingOperationsController::class, 'slaIndex'])->name('sla');
        Route::post('/sla', [\App\Http\Controllers\Web\Admin\RoutingOperationsController::class, 'storeSla'])->name('sla.store');
    });

    // Admin Users (Super Admin only)
    Route::prefix('admins')->name('admins.')->middleware('role:super_admin')->group(function () {
        Route::get('/',         [\App\Http\Controllers\Web\Admin\AdminUserController::class, 'index'])->name('index');
        Route::get('/create',   [\App\Http\Controllers\Web\Admin\AdminUserController::class, 'create'])->name('create');
        Route::post('/',        [\App\Http\Controllers\Web\Admin\AdminUserController::class, 'store'])->name('store');
        Route::get('/{id}',     [\App\Http\Controllers\Web\Admin\AdminUserController::class, 'show'])->name('show');
        Route::patch('/{id}/toggle-active', [\App\Http\Controllers\Web\Admin\AdminUserController::class, 'toggleActive'])->name('toggle-active');
        Route::patch('/{id}/promote',       [\App\Http\Controllers\Web\Admin\AdminUserController::class, 'promote'])->name('promote');
        Route::patch('/{id}/demote',        [\App\Http\Controllers\Web\Admin\AdminUserController::class, 'demote'])->name('demote');
        // Which doctors this admin is over.
        Route::put('/{id}/clinicians',      [\App\Http\Controllers\Web\Admin\AdminUserController::class, 'updateClinicians'])->name('clinicians.update');
        Route::delete('/{id}',  [\App\Http\Controllers\Web\Admin\AdminUserController::class, 'destroy'])->name('destroy');
    });

    // Offering Categories
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [AdminOfferingCategoryController::class, 'index'])->name('index');
        Route::post('/', [AdminOfferingCategoryController::class, 'store'])->name('store');
        Route::patch('/{category}/toggle', [AdminOfferingCategoryController::class, 'toggleStatus'])->name('toggle');
        Route::patch('/{category}/check-in', [AdminOfferingCategoryController::class, 'updateCheckIn'])->name('update-check-in');
        Route::delete('/{category}', [AdminOfferingCategoryController::class, 'destroy'])->name('destroy');
    });
});

// Partner Portal
Route::prefix('partner')->middleware(['auth', 'role:partner', 'partner.portal'])->name('partner.')->group(function () {
    Route::get('/dashboard', [PartnerDashboard::class, 'index'])->name('dashboard');

    // Offerings
    Route::prefix('offerings')->name('offerings.')->group(function () {
        Route::get('/', [PartnerOfferingController::class, 'index'])->name('index');
        Route::get('/create', [PartnerOfferingController::class, 'create'])->name('create');
        Route::post('/', [PartnerOfferingController::class, 'store'])->name('store');
        Route::get('/{id}', [PartnerOfferingController::class, 'show'])->name('show');
        Route::put('/{id}', [PartnerOfferingController::class, 'update'])->name('update');
        Route::delete('/{id}', [PartnerOfferingController::class, 'destroy'])->name('destroy');
        Route::patch('/{id}/toggle-status', [PartnerOfferingController::class, 'toggleStatus'])->name('toggle-status');
    });

    // Patients
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('/', [PartnerPatientController::class, 'index'])->name('index');
        Route::get('/{id}', [PartnerPatientController::class, 'show'])->name('show');
    });

    // Cases
    Route::prefix('cases')->name('cases.')->group(function () {
        Route::get('/', [PartnerCaseController::class, 'index'])->name('index');
        Route::get('/{uuid}', [PartnerCaseController::class, 'show'])->name('show');
        Route::post('/{uuid}/cancel', [PartnerCaseController::class, 'cancel'])->name('cancel');
        Route::post('/{uuid}/return-to-clinician', [PartnerCaseController::class, 'returnToClinician'])->name('return-to-clinician');
    });

    // Notifications
    Route::get('/notifications', [PartnerNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [PartnerNotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [PartnerNotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // API Credentials & Webhooks
    Route::get('/credentials', [PartnerCredentialController::class, 'show'])->name('credentials');
    Route::post('/webhooks', [PartnerCredentialController::class, 'storeWebhook'])->name('webhooks.store');
    Route::patch('/webhooks/{id}', [PartnerCredentialController::class, 'updateWebhook'])->name('webhooks.update');
    Route::delete('/webhooks/{id}', [PartnerCredentialController::class, 'destroyWebhook'])->name('webhooks.destroy');
});
