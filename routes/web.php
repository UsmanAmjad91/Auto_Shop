<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavedFilterController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceJobController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VinController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

/* ---------------- Guest authentication ---------------- */
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register.store');
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

/* ---------------- Authenticated, pre-approval / pre-verification ---------------- */
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('account/pending', [AuthController::class, 'pending'])->name('account.pending');

    Route::get('email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
    Route::get('phone/verify', [VerificationController::class, 'phoneNotice'])->name('phone.verify');
    Route::post('phone/send', [VerificationController::class, 'phoneSend'])->middleware('throttle:otp')->name('phone.send');
    Route::post('phone/verify', [VerificationController::class, 'phoneVerify'])->middleware('throttle:otp')->name('phone.verify.store');

    Route::get('two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor/challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:otp')->name('two-factor.verify');
    Route::post('two-factor/send-code', [TwoFactorController::class, 'sendCode'])->middleware('throttle:otp')->name('two-factor.send');
});

/* ---------------- Application (approved + verified + 2FA) ---------------- */
Route::middleware(['auth', 'approved', 'verified.setting', 'two-factor'])->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('dashboard/data', [DashboardController::class, 'data'])->middleware('permission:dashboard.view')->name('dashboard.data');

    // Profile & security (every signed-in user)
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('password', [ProfileController::class, 'password'])->name('password');
        Route::put('preferences', [ProfileController::class, 'preferences'])->name('preferences');
        Route::get('security', [ProfileController::class, 'security'])->name('security');
        Route::post('two-factor/totp', [TwoFactorController::class, 'setupTotp'])->name('2fa.totp');
        Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('2fa.confirm');
        Route::post('two-factor/otp', [TwoFactorController::class, 'enableOtp'])->name('2fa.otp');
        Route::delete('two-factor', [TwoFactorController::class, 'disable'])->name('2fa.disable');
        Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'regenerateCodes'])->name('2fa.codes');
        Route::delete('sessions/{session}', [ProfileController::class, 'destroySession'])->name('sessions.destroy');
        Route::delete('sessions', [ProfileController::class, 'destroyOtherSessions'])->name('sessions.others');
        Route::post('tokens', [ProfileController::class, 'createToken'])->name('tokens.store');
        Route::delete('tokens/{token}', [ProfileController::class, 'destroyToken'])->name('tokens.destroy');
    });

    // Notifications, search, saved filters, lookups for searchable selects
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::get('saved-filters/{module}', [SavedFilterController::class, 'index'])->name('saved-filters.index');
    Route::post('saved-filters/{module}', [SavedFilterController::class, 'store'])->name('saved-filters.store');
    Route::delete('saved-filters/{savedFilter}', [SavedFilterController::class, 'destroy'])->name('saved-filters.destroy');
    Route::get('lookup/{type}', [LookupController::class, 'search'])->name('lookup');
    Route::get('files/attachments/{attachment}', [FileController::class, 'attachment'])->name('files.attachment');
    Route::delete('files/attachments/{attachment}', [FileController::class, 'destroyAttachment'])->name('files.attachment.destroy');
    Route::post('files/attachments', [FileController::class, 'storeAttachment'])->name('files.attachment.store');

    // VIN decoding
    Route::post('vin/decode', [VinController::class, 'decode'])->middleware(['permission:vehicles.create|vehicles.edit', 'throttle:vin'])->name('vin.decode');

    /* ---- Vehicles ---- */
    Route::prefix('vehicles')->name('vehicles.')->controller(VehicleController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:vehicles.view')->name('index');
        Route::get('export', 'export')->middleware('permission:vehicles.export|vehicles.print')->name('export');
        Route::get('import', 'importForm')->middleware('permission:vehicles.import')->name('import');
        Route::get('import/template', 'importTemplate')->middleware('permission:vehicles.import')->name('import.template');
        Route::post('import', 'import')->middleware('permission:vehicles.import')->name('import.store');
        Route::post('bulk', 'bulk')->name('bulk');
        Route::get('create', 'create')->middleware('permission:vehicles.create')->name('create');
        Route::post('/', 'store')->middleware('permission:vehicles.create')->name('store');
        Route::get('{vehicle}', 'show')->middleware('permission:vehicles.view')->name('show');
        Route::get('{vehicle}/edit', 'edit')->middleware('permission:vehicles.edit')->name('edit');
        Route::put('{vehicle}', 'update')->middleware('permission:vehicles.edit')->name('update');
        Route::delete('{vehicle}', 'destroy')->middleware('permission:vehicles.delete')->name('destroy');
        Route::post('{vehicle}/status', 'status')->middleware('permission:vehicles.change_status|vehicles.edit')->name('status');
        Route::post('{vehicle}/images', 'uploadImages')->middleware('permission:vehicles.edit')->name('images.store');
        Route::post('{vehicle}/images/{image}/primary', 'primaryImage')->middleware('permission:vehicles.edit')->name('images.primary');
        Route::delete('{vehicle}/images/{image}', 'destroyImage')->middleware('permission:vehicles.edit')->name('images.destroy');
        Route::post('{vehicle}/documents', 'uploadDocument')->middleware('permission:vehicles.edit')->name('documents.store');
        Route::get('{vehicle}/documents/{document}', 'downloadDocument')->middleware('permission:vehicles.view')->name('documents.show');
        Route::delete('{vehicle}/documents/{document}', 'destroyDocument')->middleware('permission:vehicles.edit')->name('documents.destroy');
        Route::post('{vehicle}/expenses', 'storeExpense')->middleware('permission:vehicles.edit')->name('expenses.store');
        Route::delete('{vehicle}/expenses/{expense}', 'destroyExpense')->middleware('permission:vehicles.edit')->name('expenses.destroy');
        Route::get('{vehicle}/inspections/create', 'createInspection')->middleware('permission:vehicles.edit')->name('inspections.create');
        Route::post('{vehicle}/inspections', 'storeInspection')->middleware('permission:vehicles.edit')->name('inspections.store');
        Route::get('{vehicle}/inspections/{inspection}', 'showInspection')->middleware('permission:vehicles.view')->name('inspections.show');
        Route::get('{vehicle}/label', 'label')->middleware('permission:vehicles.print|vehicles.view')->name('label');
        Route::get('{vehicle}/sheet', 'sheet')->middleware('permission:vehicles.print|vehicles.view')->name('sheet');
    });

    /* ---- Parts & stock ---- */
    Route::prefix('parts')->name('parts.')->controller(PartController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:parts.view')->name('index');
        Route::get('export', 'export')->middleware('permission:parts.export|parts.print')->name('export');
        Route::get('movements', 'movements')->middleware('permission:parts.view')->name('movements');
        Route::get('import', 'importForm')->middleware('permission:parts.import')->name('import');
        Route::get('import/template', 'importTemplate')->middleware('permission:parts.import')->name('import.template');
        Route::post('import', 'import')->middleware('permission:parts.import')->name('import.store');
        Route::post('bulk', 'bulk')->name('bulk');
        Route::get('create', 'create')->middleware('permission:parts.create')->name('create');
        Route::post('/', 'store')->middleware('permission:parts.create')->name('store');
        Route::get('{part}', 'show')->middleware('permission:parts.view')->name('show');
        Route::get('{part}/edit', 'edit')->middleware('permission:parts.edit')->name('edit');
        Route::put('{part}', 'update')->middleware('permission:parts.edit')->name('update');
        Route::delete('{part}', 'destroy')->middleware('permission:parts.delete')->name('destroy');
        Route::post('{part}/stock', 'stock')->middleware('permission:parts.adjust_stock')->name('stock');
        Route::delete('{part}/images/{image}', 'destroyImage')->middleware('permission:parts.edit')->name('images.destroy');
        Route::get('{part}/label', 'label')->middleware('permission:parts.view')->name('label');
    });

    Route::prefix('purchase-orders')->name('purchase-orders.')->controller(PurchaseOrderController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:purchase_orders.view')->name('index');
        Route::get('export', 'export')->middleware('permission:purchase_orders.view')->name('export');
        Route::get('create', 'create')->middleware('permission:purchase_orders.create')->name('create');
        Route::post('/', 'store')->middleware('permission:purchase_orders.create')->name('store');
        Route::get('{purchaseOrder}', 'show')->middleware('permission:purchase_orders.view')->name('show');
        Route::get('{purchaseOrder}/edit', 'edit')->middleware('permission:purchase_orders.edit')->name('edit');
        Route::put('{purchaseOrder}', 'update')->middleware('permission:purchase_orders.edit')->name('update');
        Route::delete('{purchaseOrder}', 'destroy')->middleware('permission:purchase_orders.delete')->name('destroy');
        Route::post('{purchaseOrder}/order', 'markOrdered')->middleware('permission:purchase_orders.approve')->name('order');
        Route::post('{purchaseOrder}/receive', 'receive')->middleware('permission:purchase_orders.approve')->name('receive');
        Route::post('{purchaseOrder}/cancel', 'cancel')->middleware('permission:purchase_orders.approve')->name('cancel');
        Route::post('{purchaseOrder}/payments', 'pay')->middleware('permission:purchase_orders.manage_payments')->name('pay');
    });

    /* ---- Suppliers & customers ---- */
    Route::post('suppliers/bulk', [SupplierController::class, 'bulk'])->name('suppliers.bulk');
    Route::get('suppliers/export', [SupplierController::class, 'export'])->middleware('permission:suppliers.export')->name('suppliers.export');
    Route::resource('suppliers', SupplierController::class)->middlewareFor(['index', 'show'], 'permission:suppliers.view')
        ->middlewareFor(['create', 'store'], 'permission:suppliers.create')->middlewareFor(['edit', 'update'], 'permission:suppliers.edit')
        ->middlewareFor('destroy', 'permission:suppliers.delete');

    Route::post('customers/bulk', [CustomerController::class, 'bulk'])->name('customers.bulk');
    Route::get('customers/export', [CustomerController::class, 'export'])->middleware('permission:customers.export')->name('customers.export');
    Route::get('customers/{customer}/statement', [CustomerController::class, 'statement'])->middleware('permission:customers.view')->name('customers.statement');
    Route::post('customers/quick', [CustomerController::class, 'quickStore'])->middleware('permission:customers.create')->name('customers.quick');
    Route::resource('customers', CustomerController::class)->middlewareFor(['index', 'show'], 'permission:customers.view')
        ->middlewareFor(['create', 'store'], 'permission:customers.create')->middlewareFor(['edit', 'update'], 'permission:customers.edit')
        ->middlewareFor('destroy', 'permission:customers.delete');

    /* ---- Employees, attendance, payroll, tasks ---- */
    Route::post('employees/bulk', [EmployeeController::class, 'bulk'])->name('employees.bulk');
    Route::get('employees/export', [EmployeeController::class, 'export'])->middleware('permission:employees.export')->name('employees.export');
    Route::post('employees/{employee}/pay-rates', [EmployeeController::class, 'storePayRate'])->middleware('permission:employees.view_pay')->name('employees.pay-rates.store');
    Route::resource('employees', EmployeeController::class)->middlewareFor(['index', 'show'], 'permission:employees.view')
        ->middlewareFor(['create', 'store'], 'permission:employees.create')->middlewareFor(['edit', 'update'], 'permission:employees.edit')
        ->middlewareFor('destroy', 'permission:employees.delete');

    Route::prefix('attendance')->name('attendance.')->controller(AttendanceController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:attendance.view')->name('index');
        Route::get('export', 'export')->middleware('permission:attendance.export')->name('export');
        Route::post('punch', 'punch')->middleware('permission:attendance.create')->name('punch');
        Route::post('bulk', 'bulk')->name('bulk');
        Route::post('/', 'store')->middleware('permission:attendance.edit')->name('store');
        Route::put('{record}', 'update')->middleware('permission:attendance.edit')->name('update');
        Route::delete('{record}', 'destroy')->middleware('permission:attendance.delete')->name('destroy');
    });

    Route::prefix('payroll')->name('payroll.')->controller(PayrollController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:payroll.view')->name('index');
        Route::get('payments', 'payments')->middleware('permission:payroll.view')->name('payments');
        Route::get('my-payslips', 'mine')->name('mine');
        Route::post('/', 'store')->middleware('permission:payroll.create')->name('store');
        Route::get('{period}', 'show')->middleware('permission:payroll.view')->name('show');
        Route::get('{period}/export', 'export')->middleware('permission:payroll.export')->name('export');
        Route::post('{period}/generate', 'generate')->middleware('permission:payroll.edit')->name('generate');
        Route::post('{period}/approve', 'approve')->middleware('permission:payroll.approve')->name('approve');
        Route::delete('{period}', 'destroy')->middleware('permission:payroll.delete')->name('destroy');
        Route::put('entries/{entry}', 'updateEntry')->middleware('permission:payroll.edit')->name('entries.update');
        Route::post('entries/{entry}/pay', 'pay')->middleware('permission:payroll.manage_payments')->name('entries.pay');
        Route::get('entries/{entry}/payslip', 'payslip')->name('entries.payslip');
    });

    Route::prefix('tasks')->name('tasks.')->controller(TaskController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:tasks.view')->name('index');
        Route::post('/', 'store')->middleware('permission:tasks.create')->name('store');
        Route::post('bulk', 'bulk')->middleware('permission:tasks.edit|tasks.delete')->name('bulk');
        Route::get('export', 'export')->middleware('permission:tasks.view')->name('export');
        Route::put('{task}', 'update')->name('update');
        Route::delete('{task}', 'destroy')->middleware('permission:tasks.delete')->name('destroy');
    });

    /* ---- Service jobs: repair / interior / cleaning ---- */
    Route::prefix('service-jobs')->name('service-jobs.')->controller(ServiceJobController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:service_jobs.view')->name('index');
        Route::get('export', 'export')->middleware('permission:service_jobs.export')->name('export');
        Route::post('bulk', 'bulk')->middleware('permission:service_jobs.edit')->name('bulk');
        Route::get('create', 'create')->middleware('permission:service_jobs.create')->name('create');
        Route::post('/', 'store')->middleware('permission:service_jobs.create')->name('store');
        Route::get('{job}', 'show')->name('show');
        Route::get('{job}/edit', 'edit')->name('edit');
        Route::put('{job}', 'update')->name('update');
        Route::delete('{job}', 'destroy')->name('destroy');
        Route::post('{job}/status', 'status')->name('status');
        Route::post('{job}/items', 'addItem')->name('items.store');
        Route::delete('{job}/items/{item}', 'removeItem')->name('items.destroy');
        Route::post('{job}/images', 'uploadImages')->name('images.store');
        Route::delete('{job}/images/{image}', 'destroyImage')->name('images.destroy');
        Route::put('{job}/checklist', 'checklist')->name('checklist');
        Route::get('{job}/print', 'print')->name('print');
    });

    /* ---- Invoices & payments ---- */
    Route::prefix('invoices')->name('invoices.')->controller(InvoiceController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:invoices.view')->name('index');
        Route::get('export', 'export')->middleware('permission:invoices.export')->name('export');
        Route::post('bulk', 'bulk')->middleware('permission:invoices.email')->name('bulk');
        Route::get('create', 'create')->middleware('permission:invoices.create')->name('create');
        Route::post('/', 'store')->middleware('permission:invoices.create')->name('store');
        Route::get('{invoice}', 'show')->middleware('permission:invoices.view')->name('show');
        Route::get('{invoice}/edit', 'edit')->middleware('permission:invoices.edit')->name('edit');
        Route::put('{invoice}', 'update')->middleware('permission:invoices.edit')->name('update');
        Route::delete('{invoice}', 'destroy')->middleware('permission:invoices.delete')->name('destroy');
        Route::post('{invoice}/issue', 'issue')->middleware('permission:invoices.approve')->name('issue');
        Route::post('{invoice}/void', 'void')->middleware('permission:invoices.void')->name('void');
        Route::get('{invoice}/pdf', 'pdf')->middleware('permission:invoices.print|invoices.view')->name('pdf');
        Route::get('{invoice}/print', 'print')->middleware('permission:invoices.print')->name('print');
        Route::post('{invoice}/email', 'email')->middleware('permission:invoices.email')->name('email');
        Route::post('{invoice}/remind', 'remind')->middleware('permission:invoices.email')->name('remind');
        Route::post('{invoice}/financing', 'financing')->middleware('permission:payments.manage_payments')->name('financing');
        Route::post('{invoice}/financing/settle', 'settleFinancing')->middleware('permission:payments.manage_payments')->name('financing.settle');
    });

    Route::prefix('payments')->name('payments.')->controller(PaymentController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:payments.view')->name('index');
        Route::get('export', 'export')->middleware('permission:payments.export')->name('export');
        Route::get('create', 'create')->middleware('permission:payments.create')->name('create');
        Route::get('open-invoices/{customer}', 'openInvoices')->middleware('permission:payments.create')->name('open-invoices');
        Route::post('/', 'store')->middleware('permission:payments.create')->name('store');
        Route::get('{payment}', 'show')->middleware('permission:payments.view')->name('show');
        Route::get('{payment}/receipt', 'receipt')->middleware('permission:payments.view')->name('receipt');
        Route::post('{payment}/refund', 'refund')->middleware('permission:payments.refund')->name('refund');
        Route::post('{payment}/reverse', 'reverse')->middleware('permission:payments.reverse')->name('reverse');
    });

    /* ---- Reports ---- */
    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->middleware('permission:reports.view')->name('reports.show');
    Route::get('reports/{report}/export', [ReportController::class, 'export'])->middleware('permission:reports.export')->name('reports.export');

    /* ---- Administration ---- */
    Route::prefix('admin')->group(function () {
        Route::prefix('users')->name('users.')->controller(UserController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:users.view')->name('index');
            Route::get('export', 'export')->middleware('permission:users.view')->name('export');
            Route::get('create', 'create')->middleware('permission:users.create')->name('create');
            Route::post('/', 'store')->middleware('permission:users.create')->name('store');
            Route::get('{user}', 'show')->middleware('permission:users.view')->name('show');
            Route::get('{user}/edit', 'edit')->middleware('permission:users.edit')->name('edit');
            Route::put('{user}', 'update')->middleware('permission:users.edit')->name('update');
            Route::delete('{user}', 'destroy')->middleware('permission:users.delete')->name('destroy');
            Route::post('{user}/status', 'status')->middleware('permission:users.approve')->name('status');
            Route::post('bulk', 'bulk')->middleware('permission:users.approve')->name('bulk');
        });

        Route::resource('roles', RoleController::class)->except('show')
            ->middlewareFor('index', 'permission:roles.view')->middlewareFor(['create', 'store'], 'permission:roles.create')
            ->middlewareFor(['edit', 'update'], 'permission:roles.edit')->middlewareFor('destroy', 'permission:roles.delete');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit_logs.view')->name('audit-logs.index');
        Route::get('audit-logs/export', [AuditLogController::class, 'export'])->middleware('permission:audit_logs.view')->name('audit-logs.export');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:audit_logs.view')->name('audit-logs.show');

        Route::prefix('settings')->name('settings.')->controller(SettingsController::class)->middleware('permission:settings.manage')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{section}', 'section')->name('section')
                ->whereIn('section', ['general', 'theme', 'email', 'sms', 'verification', 'integrations', 'operations', 'security', 'statuses', 'lookups', 'custom-fields', 'backups', 'integration-logs']);
            Route::post('{section}', 'save')->name('save')
                ->whereIn('section', ['general', 'theme', 'email', 'sms', 'verification', 'integrations', 'operations', 'security']);
            Route::post('email/test', 'testEmail')->name('email.test');
            Route::post('sms/test', 'testSms')->name('sms.test');
            Route::post('integrations', 'storeIntegration')->name('integrations.store');
            Route::put('integrations/{integration}', 'updateIntegration')->name('integrations.update');
            Route::delete('integrations/{integration}', 'destroyIntegration')->name('integrations.destroy');
            Route::post('integrations/{integration}/test', 'testIntegration')->name('integrations.test');
            Route::post('statuses', 'storeStatus')->name('statuses.store');
            Route::put('statuses/{status}', 'updateStatus')->name('statuses.update');
            Route::delete('statuses/{status}', 'destroyStatus')->name('statuses.destroy');
            Route::post('lookups/{type}', 'storeLookup')->name('lookups.store');
            Route::delete('lookups/{type}/{id}', 'destroyLookup')->name('lookups.destroy');
            Route::post('custom-fields', 'storeCustomField')->name('custom-fields.store');
            Route::put('custom-fields/{field}', 'updateCustomField')->name('custom-fields.update');
            Route::delete('custom-fields/{field}', 'destroyCustomField')->name('custom-fields.destroy');
            Route::post('backups/run', 'runBackup')->middleware('permission:backups.manage')->name('backups.run');
            Route::get('backups/{file}', 'downloadBackup')->middleware('permission:backups.manage')->name('backups.download');
        });
    });
});
