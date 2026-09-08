<?php

use App\Http\Controllers\Webhooks\MidtransWebhookController;
use Illuminate\Support\Facades\Route;

// Public — Midtrans calls this directly, no session/auth involved.
// Excluded from CSRF verification in bootstrap/app.php.
Route::post('webhooks/midtrans', [MidtransWebhookController::class, 'handle'])
    ->name('webhooks.midtrans');
