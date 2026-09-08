<?php

use App\Http\Controllers\Admin\CustomPlanRequestController;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Support\Facades\Route;

// Cross-tenant admin panel — deliberately NOT team-scoped. See
// App\Http\Middleware\EnsurePlatformAdmin for why this doesn't use the
// existing Spatie team-permission system.
Route::middleware(['auth', 'verified', EnsurePlatformAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('custom-plan-requests', [CustomPlanRequestController::class, 'index'])
            ->name('custom-plan-requests.index');

        Route::post('custom-plan-requests/{customPlanRequest}/review', [CustomPlanRequestController::class, 'review'])
            ->name('custom-plan-requests.review');
    });
