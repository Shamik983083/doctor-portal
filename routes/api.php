<?php

use App\Http\Controllers\Api\Partner\AuthController;
use App\Http\Controllers\Api\Partner\CaseController;
use App\Http\Controllers\Api\Partner\OfferingController;
use App\Http\Controllers\Api\Partner\OrderController;
use App\Http\Controllers\Api\Partner\PatientController;
use App\Http\Controllers\Api\Partner\WebhookController;
use Illuminate\Support\Facades\Route;

// Partner OAuth2 token endpoint
Route::post('/partner/auth/token', [AuthController::class, 'token']);

// Protected Partner API
Route::prefix('partner')->middleware(['auth:api', 'partner.auth'])->group(function () {

    // Patients
    Route::prefix('patients')->group(function () {
        Route::get('/', [PatientController::class, 'index']);
        Route::post('/', [PatientController::class, 'store']);
        Route::get('/by-external-id/{externalId}', [PatientController::class, 'showByExternalId']);
        Route::get('/{id}', [PatientController::class, 'show']);
        Route::put('/{id}', [PatientController::class, 'update']);
        Route::delete('/{id}', [PatientController::class, 'destroy']);
    });

    // Cases
    Route::prefix('cases')->group(function () {
        Route::get('/', [CaseController::class, 'index']);
        Route::post('/', [CaseController::class, 'store']);
        Route::get('/by-external-id/{externalId}', [CaseController::class, 'showByExternalId']);
        Route::get('/{id}', [CaseController::class, 'show']);
        Route::post('/{id}/cancel', [CaseController::class, 'cancel']);
        Route::post('/{id}/processing', [CaseController::class, 'processing']);
        Route::post('/{id}/hold', [CaseController::class, 'setHold']);
        Route::post('/{id}/support', [CaseController::class, 'support']);
        Route::get('/{id}/events', [CaseController::class, 'events']);
    });

    // Offerings
    Route::prefix('offerings')->group(function () {
        Route::get('/', [OfferingController::class, 'index']);
        Route::post('/', [OfferingController::class, 'store']);
        Route::get('/{id}', [OfferingController::class, 'show']);
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
