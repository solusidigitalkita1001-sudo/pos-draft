<?php

use App\Http\Controllers\Organizations\CheckoutController;
use App\Http\Controllers\Organizations\CustomPlanRequestController;
use App\Http\Controllers\Organizations\OrganizationController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\Organizations\OrganizationInvoiceController;
use App\Http\Controllers\Organizations\OrganizationMemberController;
use App\Http\Controllers\Organizations\SubscriptionController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notifications.edit');
    Route::patch('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.update');

    Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');

    // ── ORGANIZATION (akun berlangganan / "Toko Saya") ────────
    Route::get('settings/organization/stores', [OrganizationController::class, 'stores'])->name('organizations.stores');
    Route::post('settings/organization/upgrade', [OrganizationController::class, 'upgrade'])->name('organizations.upgrade');

    // ── CHECKOUT (Midtrans Snap) ───────────────────────────────
    Route::post('settings/organization/checkout', [CheckoutController::class, 'store'])->name('organizations.checkout.store');
    Route::get('settings/organization/checkout/{invoice:order_id}', [CheckoutController::class, 'show'])->name('organizations.checkout.show');

    // ── CUSTOM PLAN REQUEST ─────────────────────────────────────
    Route::get('settings/organization/custom-plan-request', [CustomPlanRequestController::class, 'create'])->name('organizations.custom-plan-request.create');
    Route::post('settings/organization/custom-plan-request', [CustomPlanRequestController::class, 'store'])->name('organizations.custom-plan-request.store');

    // ── MULTI-OWNER (members & invitations) ─────────────────────
    Route::get('settings/organization/members', [OrganizationMemberController::class, 'index'])->name('organizations.members.index');
    Route::patch('settings/organization/members/{user}', [OrganizationMemberController::class, 'update'])->name('organizations.members.update');
    Route::delete('settings/organization/members/{user}', [OrganizationMemberController::class, 'destroy'])->name('organizations.members.destroy');
    Route::post('settings/organization/invitations', [OrganizationInvitationController::class, 'store'])->name('organizations.invitations.store');
    Route::post('settings/organization/invitations/{invitation}/resend', [OrganizationInvitationController::class, 'resend'])->name('organizations.invitations.resend');
    Route::delete('settings/organization/invitations/{invitation}', [OrganizationInvitationController::class, 'destroy'])->name('organizations.invitations.destroy');

    // ── SUBSCRIPTION (cancel/resume mandiri) ─────────────────────
    Route::post('settings/organization/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('organizations.subscription.cancel');
    Route::post('settings/organization/subscription/resume', [SubscriptionController::class, 'resume'])->name('organizations.subscription.resume');
    Route::post('settings/organization/subscription/downgrade', [SubscriptionController::class, 'downgrade'])->name('organizations.subscription.downgrade');
    Route::post('settings/organization/subscription/cancel-downgrade', [SubscriptionController::class, 'cancelDowngrade'])->name('organizations.subscription.cancelDowngrade');

    // ── INVOICE HISTORY ──────────────────────────────────────────
    Route::get('settings/organization/invoices', [OrganizationInvoiceController::class, 'index'])->name('organizations.invoices.index');
    Route::get('settings/organization/invoices/export', [OrganizationInvoiceController::class, 'download'])->name('organizations.invoices.download');
    Route::get('settings/organization/invoices/{invoice:order_id}/pdf', [OrganizationInvoiceController::class, 'pdf'])->name('organizations.invoices.pdf');

    Route::middleware(EnsureTeamMembership::class)->group(function () {
        Route::get('settings/teams/{team}', [TeamController::class, 'edit'])->name('teams.edit');
        Route::patch('settings/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('settings/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('settings/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');

        Route::patch('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
        Route::delete('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

        Route::post('settings/teams/{team}/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
        Route::delete('settings/teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');
    });
});
