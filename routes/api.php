<?php

use App\Http\Controllers\Api\Partner\AuthController;
use App\Http\Controllers\Api\Partner\CaseController;
use App\Http\Controllers\Api\Partner\OfferingController;
use App\Http\Controllers\Api\Partner\OrderController;
use App\Http\Controllers\Api\Partner\PatientController;
use App\Http\Controllers\Api\Partner\QuestionnaireController;
use App\Http\Controllers\Api\Partner\FileController;
use App\Http\Controllers\Api\Partner\MessageController;
use App\Http\Controllers\Api\Partner\SubStorefrontController;
use App\Http\Controllers\Api\Partner\WebhookController;
use Illuminate\Support\Facades\Route;

// Partner OAuth2 token endpoint
Route::post('/partner/auth/token', [AuthController::class, 'token']);

// Protected Partner API
Route::prefix('partner')->middleware('partner.auth')->group(function () {

    // Patients
    Route::prefix('patients')->group(function () {
        Route::get('/', [PatientController::class, 'index']);
        Route::post('/', [PatientController::class, 'store']);
        Route::get('/by-external-id/{externalId}', [PatientController::class, 'showByExternalId']);
        Route::get('/{id}', [PatientController::class, 'show']);
        Route::patch('/{id}', [PatientController::class, 'update']);
        Route::delete('/{id}', [PatientController::class, 'destroy']);
    });

    // Questionnaires — read-only; lets partners discover question IDs before submitting cases
    Route::get('/questionnaires/{uuid}', [QuestionnaireController::class, 'show']);

    // File upload — upload a prescription image, get a file_token to use in case creation
    Route::post('/files', [FileController::class, 'upload']);

    // Cases
    Route::prefix('cases')->group(function () {
        Route::get('/', [CaseController::class, 'index']);
        Route::post('/', [CaseController::class, 'store']);
        Route::get('/by-external-id/{externalId}', [CaseController::class, 'showByExternalId']);
        Route::get('/{id}', [CaseController::class, 'show']);
        Route::post('/{id}/cancel', [CaseController::class, 'cancel']);
        Route::post('/{id}/hold', [CaseController::class, 'setHold']);
        // Clinical intake push (Devin msg 2258): the storefront sends the
        // medication detail that feeds the provider review queue columns.
        Route::post('/{id}/clinical', [CaseController::class, 'updateClinical']);
        Route::post('/{id}/support', [CaseController::class, 'support']);
        Route::post('/{id}/return-to-clinician', [CaseController::class, 'returnToClinician']);
        Route::get('/{id}/events', [CaseController::class, 'events']);
        Route::get('/{id}/messages', [MessageController::class, 'index']);
        Route::post('/{id}/messages', [MessageController::class, 'store']);
    });

    // Offerings
    Route::prefix('offerings')->group(function () {
        Route::get('/', [OfferingController::class, 'index']);
        Route::post('/', [OfferingController::class, 'store']);
        Route::get('/{id}', [OfferingController::class, 'show']);
        Route::get('/{id}/questionnaires', [OfferingController::class, 'questionnaires']);
        Route::put('/{id}', [OfferingController::class, 'update']);
        Route::delete('/{id}', [OfferingController::class, 'destroy']);
    });

    // Orders
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::put('/{id}', [OrderController::class, 'update']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
    });

    // Sub-Storefronts — tenant portals call these to register new tenants
    Route::prefix('sub-storefronts')->group(function () {
        Route::get('/', [SubStorefrontController::class, 'index']);
        Route::post('/', [SubStorefrontController::class, 'store']);
    });

    // Webhooks
    Route::prefix('webhooks')->group(function () {
        Route::get('/', [WebhookController::class, 'index']);
        Route::post('/', [WebhookController::class, 'store']);
        Route::get('/{id}', [WebhookController::class, 'show']);
        Route::put('/{id}', [WebhookController::class, 'update']);
        Route::delete('/{id}', [WebhookController::class, 'destroy']);
        Route::post('/deliveries/{deliveryId}/resend', [WebhookController::class, 'resend']);
    });
});
