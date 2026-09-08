<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductStockController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionRefundController;
use App\Http\Controllers\TransactionReturnController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\EnsureTeamPermission;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use App\Http\Controllers\ProductPackageController;
use App\Http\Controllers\ProductPromotionController;
// ── PUBLIC ───────────────────────────────────────────────
Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// ── AUTH ONLY (tanpa team requirement) ───────────────────
Route::middleware(['auth'])->group(function () {
    Route::get(
        'invitations/{invitation}/accept',
        [TeamInvitationController::class, 'accept']
    )->name('invitations.accept');

    Route::get(
        'organization-invitations/{invitation}/accept',
        [OrganizationInvitationController::class, 'accept']
    )->name('organization-invitations.accept');

    Route::post('/current-team/switch/{team:slug}', function (\App\Models\Team $team) {
        $user = request()->user();
        if ($user->switchTeam($team)) {
            return redirect()
                ->route('dashboard', ['current_team' => $team->slug])
                ->with('success', "Beralih ke tim: {$team->name}");
        }
        return back()->with('error', 'Tidak dapat beralih ke tim tersebut.');
    })->name('current-team.switch');
});

// ── TEAM-SCOPED ROUTES ────────────────────────────────────
Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── USER MANAGEMENT ───────────────────────────────
        // PENTING: Gunakan {userId} bukan {user} untuk menghindari konflik
        // dengan parameter {current_team} di prefix.
        Route::prefix('users')->name('users.')->group(function () {

            Route::get('/', [UserController::class, 'index'])->name('index')
                ->middleware(EnsureTeamPermission::class.':user.view');

            // Static suffix — WAJIB di atas wildcard {userId}
            Route::get('/{userId}/edit', [UserController::class, 'edit'])->name('edit')
                ->middleware(EnsureTeamPermission::class.':user.update');

            Route::post('/{userId}/set-password', [UserController::class, 'resetUserPassword'])->name('set-password')
                ->middleware(EnsureTeamPermission::class.':user.update');

            Route::put('/{userId}', [UserController::class, 'update'])->name('update')
                ->middleware(EnsureTeamPermission::class.':user.update');

            Route::delete('/{userId}', [UserController::class, 'destroy'])->name('destroy')
                ->middleware(EnsureTeamPermission::class.':user.delete');

            // Wildcard — HARUS paling bawah
            Route::get('/{userId}', [UserController::class, 'show'])->name('show')
                ->middleware(EnsureTeamPermission::class.':user.view');
        });

        // ── INVITATIONS ───────────────────────────────────
        Route::prefix('invitations')->name('invitations.')->middleware(EnsureTeamPermission::class.':user.invite')->group(function () {
            Route::get('/', [UserController::class, 'invitations'])->name('index');
            Route::post('/', [UserController::class, 'invite'])->name('store');
            // Static suffix SEBELUM wildcard
            Route::post('/resend/{invitation}', [UserController::class, 'resendInvitation'])->name('resend');
            Route::delete('/{invitation}', [UserController::class, 'cancelInvitation'])->name('destroy');
        });

        // ── ROLE & PERMISSION ─────────────────────────────
        Route::prefix('roles')->name('roles.')->group(function () {

            Route::get('/', [RoleController::class, 'index'])->name('index')
                ->middleware(EnsureTeamPermission::class.':role.view');

            // Static suffix SEBELUM wildcard {roleId}
            Route::post('/create', [RoleController::class, 'create'])->name('create')
                ->middleware(EnsureTeamPermission::class.':role.create');

            Route::post('/', [RoleController::class, 'store'])->name('store')
                ->middleware(EnsureTeamPermission::class.':role.create');

            Route::get('/{roleId}/edit', [RoleController::class, 'edit'])->name('edit')
                ->middleware(EnsureTeamPermission::class.':role.update');

            Route::post('/{roleId}/permissions', [RoleController::class, 'syncPermissions'])->name('sync-permissions')
                ->middleware(EnsureTeamPermission::class.':permission.assign');

            Route::put('/{roleId}', [RoleController::class, 'update'])->name('update')
                ->middleware(EnsureTeamPermission::class.':role.update');

            Route::delete('/{roleId}', [RoleController::class, 'destroy'])->name('destroy')
                ->middleware(EnsureTeamPermission::class.':role.delete');

            // Wildcard — HARUS paling bawah
            Route::get('/{roleId}', [RoleController::class, 'show'])->name('show')
                ->middleware(EnsureTeamPermission::class.':role.view');
        });

        // ── PERMISSIONS ───────────────────────────────────
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index')
            ->middleware(EnsureTeamPermission::class.':permission.view');

        // ── MENUS ─────────────────────────────────────────
        Route::prefix('menus')->name('menus.')->middleware(EnsureTeamPermission::class.':role.update')->group(function () {
            Route::get('/', [MenuController::class, 'index'])->name('index');
            Route::post('/', [MenuController::class, 'store'])->name('store');
            Route::put('/{menuId}', [MenuController::class, 'update'])->name('update');
            Route::delete('/{menuId}', [MenuController::class, 'destroy'])->name('destroy');
            Route::post('/sync', [MenuController::class, 'sync'])->name('sync');
        });

        // ── PRODUCTS ──────────────────────────────────────
        Route::prefix('products')->name('products.')->group(function () {

            Route::get('/', [ProductController::class, 'index'])->name('index')
                ->middleware(EnsureTeamPermission::class.':product.view');

            Route::get('/create', [ProductController::class, 'create'])->name('create')
                ->middleware(EnsureTeamPermission::class.':product.create');

            Route::post('/', [ProductController::class, 'store'])->name('store')
                ->middleware(EnsureTeamPermission::class.':product.create');

            Route::get('/{productId}/edit', [ProductController::class, 'edit'])->name('edit')
                ->middleware(EnsureTeamPermission::class.':product.update');

            Route::get('/{productId}/history', [ProductController::class, 'history'])->name('history')
                ->middleware(EnsureTeamPermission::class.':product.view');

            Route::put('/{productId}', [ProductController::class, 'update'])->name('update')
                ->middleware(EnsureTeamPermission::class.':product.update');

            Route::delete('/{productId}', [ProductController::class, 'destroy'])->name('destroy')
                ->middleware(EnsureTeamPermission::class.':product.delete');

            // Wildcard — HARUS paling bawah
            Route::get('/{productId}', [ProductController::class, 'show'])->name('show')
                ->middleware(EnsureTeamPermission::class.':product.view');
        });

        // ── PRODUCT CATEGORIES ────────────────────────────
        Route::prefix('product-categories')->name('product-categories.')->group(function () {
            Route::get('/', [ProductCategoryController::class, 'index'])->name('index')
                ->middleware(EnsureTeamPermission::class.':product-category.view');

            Route::post('/', [ProductCategoryController::class, 'store'])->name('store')
                ->middleware(EnsureTeamPermission::class.':product-category.create');

            Route::get('/{productCategoryId}/history', [ProductCategoryController::class, 'history'])->name('history')
                ->middleware(EnsureTeamPermission::class.':product-category.view');

            Route::put('/{productCategoryId}', [ProductCategoryController::class, 'update'])->name('update')
                ->middleware(EnsureTeamPermission::class.':product-category.update');

            Route::delete('/{productCategoryId}', [ProductCategoryController::class, 'destroy'])->name('destroy')
                ->middleware(EnsureTeamPermission::class.':product-category.delete');
        });

        // ── PRODUCT STOCKS ────────────────────────────────
        Route::prefix('product-stocks')->name('product-stocks.')->group(function () {

            Route::get('/', [ProductStockController::class, 'index'])->name('index')
                ->middleware(EnsureTeamPermission::class.':product.stock.view');

            Route::post('/{productId}/adjust', [ProductStockController::class, 'adjust'])->name('adjust')
                ->middleware(EnsureTeamPermission::class.':product.stock.adjust');

            Route::get('/{productId}/history', [ProductStockController::class, 'history'])->name('history')
                ->middleware(EnsureTeamPermission::class.':product.stock.view');
        });

        // ── PRODUCT PACKAGES ──────────────────────────────────────
        Route::prefix('product-packages')->name('product-packages.')->group(function () {

            Route::get('/', [ProductPackageController::class, 'index'])->name('index')
                ->middleware(EnsureTeamPermission::class.':product-package.view');

            Route::post('/', [ProductPackageController::class, 'store'])->name('store')
                ->middleware(EnsureTeamPermission::class.':product-package.create');

            Route::get('/{productPackageId}/history', [ProductPackageController::class, 'history'])->name('history')
                ->middleware(EnsureTeamPermission::class.':product-package.view');

            Route::put('/{productPackageId}', [ProductPackageController::class, 'update'])->name('update')
                ->middleware(EnsureTeamPermission::class.':product-package.update');

            Route::delete('/{productPackageId}', [ProductPackageController::class, 'destroy'])->name('destroy')
                ->middleware(EnsureTeamPermission::class.':product-package.delete');

            Route::get('/{productPackageId}', [ProductPackageController::class, 'show'])->name('show')
                ->middleware(EnsureTeamPermission::class.':product-package.view');
        });

        // ── PRODUCT PROMOTIONS ────────────────────────────────────
        Route::prefix('product-promotions')->name('product-promotions.')->group(function () {

            Route::get('/', [ProductPromotionController::class, 'index'])->name('index')
                ->middleware(EnsureTeamPermission::class . ':product-promotion.view');

            Route::post('/', [ProductPromotionController::class, 'store'])->name('store')
                ->middleware(EnsureTeamPermission::class . ':product-promotion.create');

            // Static suffix SEBELUM wildcard {productPromotionId}
            Route::get('/{productPromotionId}/history', [ProductPromotionController::class, 'history'])->name('history')
                ->middleware(EnsureTeamPermission::class . ':product-promotion.view');

            Route::put('/{productPromotionId}', [ProductPromotionController::class, 'update'])->name('update')
                ->middleware(EnsureTeamPermission::class . ':product-promotion.update');

            Route::delete('/{productPromotionId}', [ProductPromotionController::class, 'destroy'])->name('destroy')
                ->middleware(EnsureTeamPermission::class . ':product-promotion.delete');
        });

        // ── POS / KASIR ───────────────────────────────────
        Route::prefix('pos')->name('pos.')->middleware(EnsureTeamPermission::class.':transaction.create')->group(function () {
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::get('/products/search', [PosController::class, 'searchProducts'])->name('products.search');
            Route::post('/voucher/validate', [PosController::class, 'validateVoucher'])->name('voucher.validate');
            Route::post('/promotions/evaluate', [PosController::class, 'evaluatePromotions'])->name('promotions.evaluate');
            Route::post('/transaction', [PosController::class, 'createTransaction'])->name('transaction.create');
            Route::post('/transaction/{transaction}/payment', [PosController::class, 'processPayment'])->name('transaction.payment');
            Route::post('/transaction/{transaction}/void', [PosController::class, 'voidTransaction'])->name('transaction.void');
        });

        // ── TRANSACTIONS ──────────────────────────────────
        Route::prefix('transactions')->name('transactions.')->group(function () {

            Route::get('/', [TransactionController::class, 'index'])->name('index');

            Route::get('/refunds', [TransactionRefundController::class, 'index'])->name('refunds')
                ->middleware(EnsureTeamPermission::class.':transaction.refund');

            Route::post('/refunds', [TransactionRefundController::class, 'store'])->name('refunds.store')
                ->middleware(EnsureTeamPermission::class.':transaction.refund');

            Route::delete('/refunds/{refund}', [TransactionRefundController::class, 'destroy'])->name('refunds.destroy')
                ->middleware(EnsureTeamPermission::class.':transaction.refund');

            Route::get('/returns', [TransactionReturnController::class, 'index'])->name('returns')
                ->middleware(EnsureTeamPermission::class.':transaction.return');

            Route::post('/returns', [TransactionReturnController::class, 'store'])->name('returns.store')
                ->middleware(EnsureTeamPermission::class.':transaction.return');

            Route::delete('/returns/{return}', [TransactionReturnController::class, 'destroy'])->name('returns.destroy')
                ->middleware(EnsureTeamPermission::class.':transaction.return');

            Route::post('/', [TransactionController::class, 'store'])->name('store')
                ->middleware(EnsureTeamPermission::class.':transaction.create');

            Route::get('/export', [TransactionController::class, 'export'])->name('export')
                ->middleware(EnsureTeamPermission::class.':transaction.view');

            Route::put('/{transaction}', [TransactionController::class, 'update'])->name('update')
                ->middleware(EnsureTeamPermission::class.':transaction.update');

            Route::delete('/{transaction}', [TransactionController::class, 'destroy'])->name('destroy')
                ->middleware(EnsureTeamPermission::class.':transaction.delete');
        });

        // ── VOUCHERS ──────────────────────────────────────
        Route::prefix('vouchers')->name('vouchers.')->group(function () {

            Route::get('/', [VoucherController::class, 'index'])->name('index')
                ->middleware(EnsureTeamPermission::class.':voucher.view');

            Route::post('/', [VoucherController::class, 'store'])->name('store')
                ->middleware(EnsureTeamPermission::class.':voucher.create');

            Route::put('/{voucher}', [VoucherController::class, 'update'])->name('update')
                ->middleware(EnsureTeamPermission::class.':voucher.update');

            Route::delete('/{voucher}', [VoucherController::class, 'destroy'])->name('destroy')
                ->middleware(EnsureTeamPermission::class.':voucher.delete');
        });

        // ── REPORTS ───────────────────────────────────────
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/sales', [ReportController::class, 'sales'])->name('sales')
                ->middleware(EnsureTeamPermission::class.':report.sales');
            Route::get('/stock', [ReportController::class, 'stock'])->name('stock')
                ->middleware(EnsureTeamPermission::class.':report.stock');
            Route::get('/cashier', [ReportController::class, 'cashier'])->name('cashier')
                ->middleware(EnsureTeamPermission::class.':report.cashier');
            Route::get('/export', [ReportController::class, 'export'])->name('export')
                ->middleware(EnsureTeamPermission::class.':report.export');
        });

        // // ── SETTINGS ──────────────────────────────────────
        // Route::prefix('settings')->name('settings.')->middleware(EnsureTeamPermission::class.':setting.system')->group(function () {
        //     Route::get('/system', [SettingController::class, 'system'])->name('system');
        //     Route::put('/system', [SettingController::class, 'updateSystem'])->name('system.update');
        //     Route::get('/membership', [SettingController::class, 'membership'])->name('membership')
        //         ->middleware(EnsureTeamPermission::class.':membership.manage');
        //     Route::put('/membership', [SettingController::class, 'updateMembership'])->name('membership.update')
        //         ->middleware(EnsureTeamPermission::class.':membership.manage');
        // });
    });

require __DIR__.'/settings.php';
