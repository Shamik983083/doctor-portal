<?php

use App\Http\Controllers\Web\Auth\LoginController;
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
use App\Http\Controllers\Web\Admin\FormSubmissionController as AdminFormSubmissionController;
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

Route::get('/', fn() => redirect('/login'));

// Public questionnaire form renderer — no auth required
Route::prefix('forms')->name('forms.')->group(function () {
    Route::get('/{uuid}',  [QuestionnaireFormController::class, 'show'])->name('show');
    Route::post('/{uuid}', [QuestionnaireFormController::class, 'submit'])->name('submit');
});

// Clinician Portal
Route::prefix('clinician')->middleware(['auth', 'role:clinician|admin'])->name('clinician.')->group(function () {
    Route::get('/dashboard', [ClinicianDashboard::class, 'index'])->name('dashboard');

    Route::prefix('cases')->name('cases.')->group(function () {
        Route::get('/queue', [ClinicianCaseController::class, 'queue'])->name('queue');
        Route::get('/{uuid}', [ClinicianCaseController::class, 'show'])->name('show');
        Route::post('/{uuid}/assign', [ClinicianCaseController::class, 'assign'])->name('assign');
        Route::get('/{uuid}/prescribe', [ClinicianCaseController::class, 'prescribeForm'])->name('prescribe.form');
        Route::post('/{uuid}/prescribe', [ClinicianCaseController::class, 'prescribe'])->name('prescribe');
        Route::post('/{uuid}/approve', [ClinicianCaseController::class, 'approve'])->name('approve');
        Route::post('/{uuid}/cancel', [ClinicianCaseController::class, 'cancel'])->name('cancel');
        Route::post('/{uuid}/support', [ClinicianCaseController::class, 'escalateToSupport'])->name('support');
        Route::post('/{uuid}/notes', [ClinicianCaseController::class, 'addNote'])->name('notes.store');
        Route::post('/{uuid}/messages', [ClinicianCaseController::class, 'sendMessage'])->name('messages.store');
    });

    Route::get('/queue', [ClinicianCaseController::class, 'queue'])->name('queue');
});

// Admin Console
Route::prefix('admin')->middleware(['auth', 'role:admin'])->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    // Patients
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('/', [AdminPatientController::class, 'index'])->name('index');
        Route::get('/{id}', [AdminPatientController::class, 'show'])->name('show');
    });

    // Cases
    Route::prefix('cases')->name('cases.')->group(function () {
        Route::get('/', [AdminCaseController::class, 'index'])->name('index');
        Route::get('/{uuid}', [AdminCaseController::class, 'show'])->name('show');
        Route::post('/{uuid}/assign', [AdminCaseController::class, 'assign'])->name('assign');
    });

    // Partners
    Route::prefix('partners')->name('partners.')->group(function () {
        Route::get('/', [AdminPartnerController::class, 'index'])->name('index');
        Route::get('/create', [AdminPartnerController::class, 'create'])->name('create');
        Route::post('/', [AdminPartnerController::class, 'store'])->name('store');
        Route::get('/{id}', [AdminPartnerController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [AdminPartnerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AdminPartnerController::class, 'update'])->name('update');
        Route::get('/{id}/users/create', [AdminPartnerController::class, 'createUser'])->name('users.create');
        Route::post('/{id}/users', [AdminPartnerController::class, 'storeUser'])->name('users.store');
        Route::post('/{id}/regenerate-credentials', [AdminPartnerController::class, 'regenerateCredentials'])->name('regenerate-credentials');
    });

    // Clinicians
    Route::prefix('clinicians')->name('clinicians.')->group(function () {
        Route::get('/', [AdminClinicianController::class, 'index'])->name('index');
        Route::get('/create', [AdminClinicianController::class, 'create'])->name('create');
        Route::post('/', [AdminClinicianController::class, 'store'])->name('store');
        Route::get('/{id}', [AdminClinicianController::class, 'show'])->name('show');
        Route::put('/{id}', [AdminClinicianController::class, 'update'])->name('update');
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
    Route::prefix('offerings')->name('offerings.')->group(function () {
        Route::get('/', [AdminOfferingController::class, 'index'])->name('index');
        Route::get('/create', [AdminOfferingController::class, 'create'])->name('create');
        Route::post('/', [AdminOfferingController::class, 'store'])->name('store');
        Route::get('/{id}', [AdminOfferingController::class, 'show'])->name('show');
        Route::put('/{id}', [AdminOfferingController::class, 'update'])->name('update');
        Route::delete('/{id}', [AdminOfferingController::class, 'destroy'])->name('destroy');
        Route::patch('/{id}/toggle-status', [AdminOfferingController::class, 'toggleStatus'])->name('toggle-status');
    });

    // Form Submissions (standalone questionnaire responses without a case)
    Route::prefix('form-submissions')->name('form-submissions.')->group(function () {
        Route::get('/', [AdminFormSubmissionController::class, 'index'])->name('index');
        Route::get('/{id}', [AdminFormSubmissionController::class, 'show'])->name('show');
        Route::get('/{id}/create-case', [AdminFormSubmissionController::class, 'createCase'])->name('create-case');
        Route::post('/{id}/create-case', [AdminFormSubmissionController::class, 'storeCase'])->name('store-case');
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
        Route::post('/{uuid}/processing', [PartnerCaseController::class, 'processing'])->name('processing');
    });

    // API Credentials & Webhooks
    Route::get('/credentials', [PartnerCredentialController::class, 'show'])->name('credentials');
});
