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
use App\Http\Controllers\Web\Admin\ClinicianController as AdminClinicianController;
use App\Http\Controllers\Web\Admin\OfferingController as AdminOfferingController;
use App\Http\Controllers\Web\Admin\OfferingCategoryController as AdminOfferingCategoryController;
use App\Http\Controllers\Web\Admin\QuestionnaireController as AdminQuestionnaireController;
use App\Http\Controllers\Web\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Web\Admin\WebhookDeliveryController as AdminWebhookDeliveryController;
use App\Http\Controllers\Web\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Web\Admin\TriageRuleController as AdminTriageRuleController;
use App\Http\Controllers\Web\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Web\Clinician\NotificationController as ClinicianNotificationController;
use App\Http\Controllers\Web\Partner\NotificationController as PartnerNotificationController;
use App\Http\Controllers\Web\Form\QuestionnaireFormController;
use App\Http\Controllers\Web\MaPortalController;
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

/*
 * MA-Portal role-view showcase.
 *
 * WAS `auth` ONLY, WITH NO ROLE GATE. Defensible when these were static
 * mockups, but MaPortalController reads real records: practitioner() loads open
 * cases with patient demographics, recorded intake answers, clinical notes and
 * messages; superAdmin() lists every user in the install with email and roles.
 * Behind `auth` alone, a PARTNER login (an external storefront operator) could
 * read all of it by typing the URL.
 *
 * Each route is now gated to the tier whose surface it previews, mirroring the
 * real portals below. The bare /ma-portal redirect stays on `auth` only because
 * it just forwards; the destination enforces its own gate.
 */
Route::prefix('ma-portal')->middleware(['auth'])->name('ma-portal.')->group(function () {
    Route::get('/', fn () => redirect()->route('ma-portal.practitioner'));

    Route::get('/practitioner', [MaPortalController::class, 'practitioner'])
        ->middleware('role:clinician|admin|super_admin')->name('practitioner');

    Route::get('/admin', [MaPortalController::class, 'admin'])
        ->middleware('role:admin|super_admin')->name('admin');

    // Global user roster and cross-tenant view. Super admin only, matching the
    // real admin.admins screens.
    Route::get('/super-admin', [MaPortalController::class, 'superAdmin'])
        ->middleware('role:super_admin')->name('super-admin');
});

// Clinician Portal
/*
 * `clinician.portal` guarantees a Clinician record exists behind the user, so
 * the controllers' `Auth::user()->clinician` cannot be null. The role gate still
 * says who may knock; this says who has a queue to show.
 */
Route::prefix('clinician')->middleware(['auth', 'role:clinician|admin', 'clinician.portal'])->name('clinician.')->group(function () {
    Route::get('/dashboard', [ClinicianDashboard::class, 'index'])->name('dashboard');

    Route::prefix('cases')->name('cases.')->group(function () {
        Route::get('/queue', [ClinicianCaseController::class, 'queue'])->name('queue');
        Route::get('/my-cases', [ClinicianCaseController::class, 'myCases'])->name('my-cases');
        Route::get('/{uuid}', [ClinicianCaseController::class, 'show'])->name('show');
        Route::post('/{uuid}/assign', [ClinicianCaseController::class, 'assign'])->name('assign');
        Route::get('/{uuid}/prescribe', [ClinicianCaseController::class, 'prescribeForm'])->name('prescribe.form');
        Route::post('/{uuid}/prescribe', [ClinicianCaseController::class, 'prescribe'])->name('prescribe');
        Route::post('/{uuid}/approve', [ClinicianCaseController::class, 'approve'])->name('approve');
        // Returns a draft for the provider to edit. Persists nothing, sends nothing.
        Route::post('/{uuid}/draft-note', [ClinicianCaseController::class, 'draftNote'])->name('draft-note');
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
    });

    // Clinicians
    Route::prefix('clinicians')->name('clinicians.')->group(function () {
        Route::get('/', [AdminClinicianController::class, 'index'])->name('index');
        Route::get('/create', [AdminClinicianController::class, 'create'])->name('create');
        Route::post('/', [AdminClinicianController::class, 'store'])->name('store');
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
    Route::prefix('questionnaires')->name('questionnaires.')->group(function () {
        Route::get('/', [AdminQuestionnaireController::class, 'index'])->name('index');
        Route::get('/create', [AdminQuestionnaireController::class, 'create'])->name('create');
        Route::post('/', [AdminQuestionnaireController::class, 'store'])->name('store');
        Route::get('/{id}', [AdminQuestionnaireController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [AdminQuestionnaireController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AdminQuestionnaireController::class, 'update'])->name('update');
        Route::delete('/{id}', [AdminQuestionnaireController::class, 'destroy'])->name('destroy');
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
    Route::get('/guide/weightloss-api', function () {
        $questionnaire = \App\Models\Questionnaire::with([
            'questions' => fn($q) => $q->where('is_active', true)->orderBy('step_number')->orderBy('sort_order'),
        ])->where('name', 'MWL · Weight Loss')->first();
        return view('admin.guide.weightloss-api', compact('questionnaire'));
    })->name('guide.weightloss-api');

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

    // SLA Settings
    Route::get('/settings',  [AdminSettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');

    // Triage Rule Set
    Route::prefix('triage-rules')->name('triage-rules.')->group(function () {
        Route::get('/',           [AdminTriageRuleController::class, 'index'])->name('index');
        Route::post('/',          [AdminTriageRuleController::class, 'store'])->name('store');
        Route::put('/{triageRule}',    [AdminTriageRuleController::class, 'update'])->name('update');
        Route::delete('/{triageRule}', [AdminTriageRuleController::class, 'destroy'])->name('destroy');
        Route::patch('/{triageRule}/toggle', [AdminTriageRuleController::class, 'toggleActive'])->name('toggle');
    });

    // Case routing policy. Decides which doctor sees which patient, so it sits
    // with the other super-admin configuration.
    Route::prefix('routing')->name('routing.')->group(function () {
        Route::get('/',  [\App\Http\Controllers\Web\Admin\RoutingPolicyController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Web\Admin\RoutingPolicyController::class, 'store'])->name('store');
        Route::post('/{id}/activate', [\App\Http\Controllers\Web\Admin\RoutingPolicyController::class, 'activate'])->name('activate');
    });

    }); // end super-admin-only integration and configuration group

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
