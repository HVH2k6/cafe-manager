<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\DiningTableStatusController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeePasswordController;
use App\Http\Controllers\EmployeeStatusController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayosPaymentController;
use App\Http\Controllers\PayosWebhookController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductBundleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Qr\QrMenuController;
use App\Http\Controllers\Qr\QrOrderController;
use App\Http\Controllers\Qr\QrSessionController;
use App\Http\Controllers\QrCallController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\QrOperatingHourController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SidebarBadgeController;
use App\Http\Controllers\StaffAlertController;
use App\Http\Middleware\EnsureQrSessionIsUsable;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// E-receipt opened by the customer's phone: no login, the URL signature is checked instead.
Route::get('receipts/{order}', [ReceiptController::class, 'showPublic'])->name('receipts.public')->middleware(['signed:relative', 'throttle:60,1']);

// Called by payOS servers: no login, no CSRF token; the request is verified by its HMAC signature.
Route::post('webhooks/payos', PayosWebhookController::class)->name('payos.webhook')->middleware('throttle:120,1');

/*
 * Ordering by QR (step 9): customers do not log in. The table's QR token opens
 * the landing page; the random session token in /s/... is their proof after that.
 * Rate limits are defined in AppServiceProvider.
 */
Route::get('qr/{qrCode:qr_token}', [QrSessionController::class, 'show'])->name('qr.show')->middleware('throttle:60,1')
    ->missing(fn () => response()->view('qr.unavailable', ['message' => 'Mã QR không còn hiệu lực. Vui lòng quét mã đang dán trên bàn hoặc gọi nhân viên.', 'tableNo' => null], 404));
Route::post('qr/{qrCode:qr_token}/sessions', [QrSessionController::class, 'store'])->name('qr.sessions.store')->middleware('throttle:qr-start');

Route::prefix('s/{qrSession}')->name('qr.')->group(function () {
    Route::get('order', [QrOrderController::class, 'show'])->name('order')->middleware('throttle:60,1');
    Route::get('pay/status', [QrOrderController::class, 'payStatus'])->name('pay.status')->middleware('throttle:qr-poll');
    Route::get('pay/return', [QrOrderController::class, 'payReturn'])->name('pay.return')->middleware('throttle:qr-poll');

    Route::middleware(EnsureQrSessionIsUsable::class)->group(function () {
        Route::get('/', [QrMenuController::class, 'show'])->name('menu')->middleware('throttle:60,1');
        Route::put('cart', [QrMenuController::class, 'updateCart'])->name('cart.update')->middleware('throttle:qr-cart');
        Route::post('orders', [QrMenuController::class, 'store'])->name('orders.store')->middleware('throttle:qr-order');
        Route::post('call', [QrOrderController::class, 'callStaff'])->name('call')->middleware('throttle:qr-call');
        Route::post('pay', [QrOrderController::class, 'pay'])->name('pay')->middleware('throttle:qr-pay');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::view('forgot-password', 'auth.forgot-password')->name('password.request');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    // Each count inside is filtered by the user's permissions (SidebarBadges).
    Route::get('sidebar-badges', SidebarBadgeController::class)->name('sidebar-badges')->middleware('throttle:60,1');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index')->middleware('can:employees.view');
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create')->middleware('can:employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store')->middleware('can:employees.create');
    Route::get('employees/{user}/edit', [EmployeeController::class, 'edit'])->name('employees.edit')->middleware('can:employees.update');
    Route::put('employees/{user}', [EmployeeController::class, 'update'])->name('employees.update')->middleware('can:employees.update');
    Route::put('employees/{user}/password', [EmployeePasswordController::class, 'update'])->name('employees.password.update')->middleware('can:employees.update');
    Route::put('employees/{user}/status', [EmployeeStatusController::class, 'update'])->name('employees.status.update')->middleware('can:employees.delete');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index')->middleware('can:orders.view');
    Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create')->middleware('can:orders.create');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store')->middleware('can:orders.create');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show')->middleware('can:orders.view');
    Route::put('orders/{order}/discount', [OrderController::class, 'updateDiscount'])->name('orders.discount.update')->middleware('can:orders.discount');
    Route::get('orders/{order}/items/create', [OrderController::class, 'createItems'])->name('orders.items.create')->middleware('can:orders.update');
    Route::post('orders/{order}/items', [OrderItemController::class, 'store'])->name('orders.items.store')->middleware('can:orders.update');
    Route::delete('orders/{order}/items/{item}', [OrderItemController::class, 'destroy'])->name('orders.items.destroy')->middleware('can:orders.update')->scopeBindings();
    Route::put('orders/{order}/confirm', [OrderStatusController::class, 'confirm'])->name('orders.confirm')->middleware('can:orders.update');
    Route::put('orders/{order}/serve', [OrderStatusController::class, 'serve'])->name('orders.serve')->middleware('can:orders.update');
    Route::put('orders/{order}/cancel', [OrderStatusController::class, 'cancel'])->name('orders.cancel')->middleware('can:orders.cancel');

    Route::get('orders/{order}/receipt', [ReceiptController::class, 'show'])->name('orders.receipt')->middleware('can:orders.view');

    Route::middleware('can:payments.refund')->group(function () {
        Route::post('payments/{payment}/refund', [RefundController::class, 'store'])->name('payments.refund');
        Route::post('payos/{link:order_code}/refund', [RefundController::class, 'storeForPayosLink'])->name('payos.refund');
    });

    Route::middleware('can:payments.create')->group(function () {
        Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->name('orders.payments.store');
        Route::post('orders/{order}/payos', [PayosPaymentController::class, 'store'])->name('orders.payos.store');
        Route::get('payos/{link:order_code}/status', [PayosPaymentController::class, 'status'])->name('payos.status');
        Route::post('payos/{link:order_code}/cancel', [PayosPaymentController::class, 'cancel'])->name('payos.cancel');
        Route::get('payos/{link:order_code}/return', [PayosPaymentController::class, 'returned'])->name('payos.return');
    });

    Route::get('kitchen', [KitchenController::class, 'index'])->name('kitchen.index')->middleware('can:kitchen.view');
    Route::put('kitchen/items/{item}', [KitchenController::class, 'update'])->name('kitchen.items.update')->middleware('can:kitchen.update');

    // Products, categories and combos share the products.* permissions.
    foreach (['products' => ProductController::class, 'categories' => CategoryController::class, 'bundles' => ProductBundleController::class] as $name => $controller) {
        Route::resource($name, $controller)
            ->except('show')
            ->middlewareFor('index', 'can:products.view')
            ->middlewareFor(['create', 'store'], 'can:products.create')
            ->middlewareFor(['edit', 'update'], 'can:products.update')
            ->middlewareFor('destroy', 'can:products.delete');
    }

    Route::resource('tables', DiningTableController::class)
        ->except('show')
        ->middlewareFor('index', 'can:tables.view')
        ->middlewareFor(['create', 'store'], 'can:tables.create')
        ->middlewareFor(['edit', 'update'], 'can:tables.update')
        ->middlewareFor('destroy', 'can:tables.delete');
    Route::put('tables/{table}/status', [DiningTableStatusController::class, 'update'])->name('tables.status.update')->middleware('can:tables.status');

    Route::middleware('can:tables.update')->group(function () {
        Route::get('qr-codes', [QrCodeController::class, 'index'])->name('tables.qr.index');
        Route::post('qr-codes', [QrCodeController::class, 'storeMissing'])->name('tables.qr.store-missing');
        Route::get('tables/{table}/qr', [QrCodeController::class, 'show'])->name('tables.qr.show');
        Route::post('tables/{table}/qr', [QrCodeController::class, 'store'])->name('tables.qr.store');
        Route::put('tables/{table}/qr/status', [QrCodeController::class, 'updateStatus'])->name('tables.qr.status');
    });

    Route::post('qr-calls/{call}/answer', [QrCallController::class, 'answer'])->name('qr-calls.answer')->middleware('can:orders.view');
    Route::get('staff-alerts', StaffAlertController::class)->name('staff-alerts')->middleware('can:orders.view');

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.general.edit')->middleware('can:settings.view');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.general.update')->middleware('can:settings.update');
    Route::get('settings/activity', [ActivityLogController::class, 'index'])->name('settings.activity')->middleware('can:settings.activity');

    Route::get('settings/qr-hours', [QrOperatingHourController::class, 'edit'])->name('settings.qr-hours.edit')->middleware('can:settings.view');
    Route::put('settings/qr-hours', [QrOperatingHourController::class, 'update'])->name('settings.qr-hours.update')->middleware('can:settings.update');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index')->middleware('can:reports.view');
    Route::get('reports/export/{type}', [ReportController::class, 'export'])->name('reports.export')->middleware('can:reports.export')
        ->whereIn('type', array_keys(ReportController::EXPORTS));

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index')->middleware('can:inventory.view');
    Route::get('inventory/{product}', [InventoryController::class, 'show'])->name('inventory.show')->middleware('can:inventory.view');
    Route::post('inventory/{product}', [InventoryController::class, 'store'])->name('inventory.store')->middleware('can:inventory.update');
    Route::put('inventory/{product}', [InventoryController::class, 'update'])->name('inventory.update')->middleware('can:inventory.update');
    Route::post('inventory/{product}/transactions', [InventoryTransactionController::class, 'store'])->name('inventory.transactions.store')->middleware('can:inventory.update');

    Route::middleware('can:manage-permissions')->group(function () {
        Route::resource('permissions', PermissionController::class)->except('show');
        Route::put('role-permissions', [RolePermissionController::class, 'update'])->name('role-permissions.update');
        Route::resource('roles', RoleController::class)->except('show');
    });
});
