<?php

use App\Http\Controllers\Payment\PaymentWebhookController;
use App\Http\Controllers\Telegram\WebhookController;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

// Configure rate limiter for webhook endpoint
RateLimiter::for('webhook', function (Request $request) {
    return Limit::perMinute(60)->by($request->ip());
});

// Telegram webhook
Route::post('/telegram/webhook/{tenantToken}', [WebhookController::class, 'handle'])
    ->middleware('telegram.hmac');

// Payment webhook (generic)
Route::post('/payment/webhook', [PaymentWebhookController::class, 'handle'])
    ->middleware('throttle:webhook');

// ABA PayWay webhook (dedicated endpoint — ABA sends pushback here)
Route::post('/payment/webhook/aba', [PaymentWebhookController::class, 'handleAba'])
    ->middleware('throttle:webhook');

// Tenant payment API routes (for borrower-facing payment requests)
Route::prefix('tenant')->middleware(['auth:sanctum'])->group(function () {
    // Initiate payment request (creates pending payment with reference code)
    Route::post('/loans/{loan}/payments/initiate', [App\Http\Controllers\Tenant\PaymentController::class, 'initiatePayment'])
        ->name('api.payments.initiate');

    // Check payment status (borrower polls to see if payment was received)
    Route::get('/payments/{payment}/status', [App\Http\Controllers\Tenant\PaymentController::class, 'checkPaymentStatus'])
        ->name('api.payments.status');
});

// Testing routes (NO AUTHENTICATION - for demo/testing only)
// TODO: Remove or secure these routes in production!
Route::prefix('test')->group(function () {
    Route::post('/loans/{loan}/payments/initiate', [App\Http\Controllers\Tenant\PaymentController::class, 'initiatePayment'])
        ->name('api.test.payments.initiate');

    Route::get('/payments/{paymentId}/status', [App\Http\Controllers\Tenant\PaymentController::class, 'checkPaymentStatus'])
        ->name('api.test.payments.status');
});
