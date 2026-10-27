<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\ForcedPasswordController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientPurchaseOrderController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CurrentCompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EodAttachmentController;
use App\Http\Controllers\EodReportController;
use App\Http\Controllers\PayrollBulkPayslipController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollExportController;
use App\Http\Controllers\PayrollPayslipController;
use App\Http\Controllers\ProductServiceController;
use App\Http\Controllers\PublicProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReceivingReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskLabelController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WorkBoardColumnController;
use App\Http\Controllers\WorkBoardController;
use App\Http\Controllers\WorkTaskController;
use App\Http\Controllers\WorkTaskMoveController;
use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\ReceivingReceipt;
use App\Models\Vendor;
use App\Models\WorkBoard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::get('product/{publicToken}', [PublicProductController::class, 'show'])
    ->name('public-products.show');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('change-password', [ForcedPasswordController::class, 'edit'])
        ->name('password.change.edit');
    Route::put('change-password', [ForcedPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.change.update');
});

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('can:dashboard.view')
        ->name('dashboard');

    Route::get('reports', [ReportController::class, 'index'])
        ->middleware('can:reports.view')
        ->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])
        ->middleware('can:reports.view')
        ->name('reports.export');

    Route::patch('current-company/{company}', [CurrentCompanyController::class, 'update'])
        ->name('current-company.update');

    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('can:audit-logs.view')
        ->name('audit-logs.index');

    Route::get('documents', [DocumentController::class, 'index'])
        ->middleware('can:viewAny,'.Document::class)
        ->name('documents.index');
    Route::get('documents/create', [DocumentController::class, 'create'])
        ->middleware('can:create,'.Document::class)
        ->name('documents.create');
    Route::post('documents', [DocumentController::class, 'store'])
        ->middleware('can:documents.upload')
        ->name('documents.store');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware('can:download,document')
        ->name('documents.download');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
        ->middleware('can:delete,document')
        ->name('documents.destroy');

    Route::patch('companies/{company}/activate', [CompanyController::class, 'activate'])
        ->middleware('can:update,company')
        ->name('companies.activate');
    Route::patch('companies/{company}/deactivate', [CompanyController::class, 'deactivate'])
        ->middleware('can:update,company')
        ->name('companies.deactivate');

    Route::resource('companies', CompanyController::class)
        ->middlewareFor(['index', 'show'], 'can:viewAny,'.Company::class)
        ->middlewareFor(['create', 'store'], 'can:create,'.Company::class)
        ->middlewareFor(['edit', 'update'], 'can:update,company')
        ->middlewareFor('destroy', 'can:delete,company');

    Route::patch('clients/{client}/activate', [ClientController::class, 'activate'])
        ->middleware('can:update,client')
        ->name('clients.activate');
    Route::patch('clients/{client}/deactivate', [ClientController::class, 'deactivate'])
        ->middleware('can:update,client')
        ->name('clients.deactivate');

    Route::resource('clients', ClientController::class)
        ->except('destroy')
        ->middlewareFor('index', 'can:viewAny,'.Client::class)
        ->middlewareFor('show', 'can:view,client')
        ->middlewareFor(['create', 'store'], 'can:create,'.Client::class)
        ->middlewareFor(['edit', 'update'], 'can:update,client');

    Route::patch('vendors/{vendor}/activate', [VendorController::class, 'activate'])
        ->middleware('can:update,vendor')
        ->name('vendors.activate');
    Route::patch('vendors/{vendor}/deactivate', [VendorController::class, 'deactivate'])
        ->middleware('can:update,vendor')
        ->name('vendors.deactivate');

    Route::resource('vendors', VendorController::class)
        ->except('destroy')
        ->middlewareFor('index', 'can:viewAny,'.Vendor::class)
        ->middlewareFor('show', 'can:view,vendor')
        ->middlewareFor(['create', 'store'], 'can:create,'.Vendor::class)
        ->middlewareFor(['edit', 'update'], 'can:update,vendor');

    Route::patch('product-services/{product_service}/activate', [ProductServiceController::class, 'activate'])
        ->middleware('can:update,product_service')
        ->name('product-services.activate');
    Route::patch('product-services/{product_service}/deactivate', [ProductServiceController::class, 'deactivate'])
        ->middleware('can:update,product_service')
        ->name('product-services.deactivate');
    Route::get('product-services/qr-print/bulk', [ProductServiceController::class, 'bulkQrPrint'])
        ->middleware('can:viewAny,'.ProductService::class)
        ->name('product-services.bulk-qr-print');
    Route::get('product-services/{product_service}/qr-print', [ProductServiceController::class, 'qrPrint'])
        ->middleware('can:view,product_service')
        ->name('product-services.qr-print');

    Route::resource('product-services', ProductServiceController::class)
        ->except('destroy')
        ->middlewareFor('index', 'can:viewAny,'.ProductService::class)
        ->middlewareFor('show', 'can:view,product_service')
        ->middlewareFor(['create', 'store'], 'can:create,'.ProductService::class)
        ->middlewareFor(['edit', 'update'], 'can:update,product_service');

    Route::resource('employees', EmployeeController::class)
        ->except('show')
        ->middlewareFor('index', 'can:viewAny,'.Employee::class)
        ->middlewareFor(['create', 'store'], 'can:create,'.Employee::class)
        ->middlewareFor(['edit', 'update'], 'can:update,employee')
        ->middlewareFor('destroy', 'can:delete,employee');

    Route::scopeBindings()->group(function (): void {
        Route::post('work-boards/{work_board}/columns', [WorkBoardColumnController::class, 'store'])
            ->name('work-board-columns.store');
        Route::delete('work-boards/{work_board}/columns/{column}', [WorkBoardColumnController::class, 'destroy'])
            ->name('work-board-columns.destroy');
        Route::post('work-boards/{work_board}/labels', [TaskLabelController::class, 'store'])
            ->name('task-labels.store');
        Route::post('work-boards/{work_board}/tasks', [WorkTaskController::class, 'store'])
            ->name('work-tasks.store');
        Route::patch('work-boards/{work_board}/tasks/{work_task}', [WorkTaskController::class, 'update'])
            ->name('work-tasks.update');
        Route::patch('work-boards/{work_board}/tasks/{work_task}/move', WorkTaskMoveController::class)
            ->name('work-tasks.move');
        Route::delete('work-boards/{work_board}/tasks/{work_task}', [WorkTaskController::class, 'destroy'])
            ->name('work-tasks.destroy');
        Route::post('work-boards/{work_board}/tasks/{work_task}/eod', [EodReportController::class, 'store'])
            ->name('eod-reports.store');
    });
    Route::get('eod-attachments/{eodAttachment}', EodAttachmentController::class)
        ->name('eod-attachments.show');
    Route::resource('work-boards', WorkBoardController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middlewareFor('index', 'can:viewAny,'.WorkBoard::class)
        ->middlewareFor('show', 'can:view,work_board')
        ->middlewareFor('update', 'can:update,work_board')
        ->middlewareFor('destroy', 'can:delete,work_board');

    Route::patch('payrolls/{payroll}/finalize', [PayrollController::class, 'finalize'])
        ->middleware('can:finalize,payroll')
        ->name('payrolls.finalize');
    Route::patch('payrolls/{payroll}/reopen', [PayrollController::class, 'reopen'])
        ->middleware('can:reopen,payroll')
        ->name('payrolls.reopen');
    Route::get('payrolls/{payroll}/items/{payrollItem}/payslip', PayrollPayslipController::class)
        ->name('payrolls.payslips.show');
    Route::get('payrolls/{payroll}/payslips/print', PayrollBulkPayslipController::class)
        ->middleware('can:printPayslip,payroll')
        ->name('payrolls.payslips.bulk');
    Route::get('payrolls/{payroll}/export', PayrollExportController::class)
        ->middleware('can:export,payroll')
        ->name('payrolls.export');
    Route::resource('payrolls', PayrollController::class)
        ->only(['index', 'create', 'store', 'show', 'update', 'destroy'])
        ->middlewareFor('index', 'can:viewAny,'.Payroll::class)
        ->middlewareFor('show', 'can:view,payroll')
        ->middlewareFor(['create', 'store'], 'can:create,'.Payroll::class)
        ->middlewareFor('update', 'can:update,payroll')
        ->middlewareFor('destroy', 'can:delete,payroll');

    Route::patch('quotations/{quotation}/submit', [QuotationController::class, 'submit'])
        ->middleware('can:submit,quotation')
        ->name('quotations.submit');
    Route::patch('quotations/{quotation}/approve', [QuotationController::class, 'approve'])
        ->middleware('can:approve,quotation')
        ->name('quotations.approve');
    Route::patch('quotations/{quotation}/reject', [QuotationController::class, 'reject'])
        ->middleware('can:reject,quotation')
        ->name('quotations.reject');
    Route::patch('quotations/{quotation}/cancel', [QuotationController::class, 'cancel'])
        ->middleware('can:update,quotation')
        ->name('quotations.cancel');
    Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])
        ->middleware('can:print,quotation')
        ->name('quotations.print');

    Route::resource('quotations', QuotationController::class)
        ->middlewareFor('index', 'can:viewAny,'.Quotation::class)
        ->middlewareFor('show', 'can:view,quotation')
        ->middlewareFor(['create', 'store'], 'can:create,'.Quotation::class)
        ->middlewareFor(['edit', 'update'], 'can:update,quotation')
        ->middlewareFor('destroy', 'can:delete,quotation');

    Route::resource('client-pos', ClientPurchaseOrderController::class)
        ->parameters(['client-pos' => 'client_purchase_order'])
        ->middlewareFor('index', 'can:viewAny,'.ClientPurchaseOrder::class)
        ->middlewareFor('show', 'can:view,client_purchase_order')
        ->middlewareFor(['create', 'store'], 'can:create,'.ClientPurchaseOrder::class)
        ->middlewareFor(['edit', 'update'], 'can:update,client_purchase_order')
        ->middlewareFor('destroy', 'can:delete,client_purchase_order');
    Route::patch('client-pos/{client_purchase_order}/process', [ClientPurchaseOrderController::class, 'process'])
        ->middleware('can:update,client_purchase_order')
        ->name('client-pos.process');
    Route::patch('client-pos/{client_purchase_order}/fulfill', [ClientPurchaseOrderController::class, 'fulfill'])
        ->middleware('can:fulfill,client_purchase_order')
        ->name('client-pos.fulfill');
    Route::patch('client-pos/{client_purchase_order}/complete', [ClientPurchaseOrderController::class, 'complete'])
        ->middleware('can:update,client_purchase_order')
        ->name('client-pos.complete');
    Route::patch('client-pos/{client_purchase_order}/cancel', [ClientPurchaseOrderController::class, 'cancel'])
        ->middleware('can:cancel,client_purchase_order')
        ->name('client-pos.cancel');

    Route::patch('purchase-requests/{purchase_request}/submit', [PurchaseRequestController::class, 'submit'])
        ->middleware('can:submit,purchase_request')
        ->name('purchase-requests.submit');
    Route::patch('purchase-requests/{purchase_request}/approve', [PurchaseRequestController::class, 'approve'])
        ->middleware('can:approve,purchase_request')
        ->name('purchase-requests.approve');
    Route::patch('purchase-requests/{purchase_request}/reject', [PurchaseRequestController::class, 'reject'])
        ->middleware('can:reject,purchase_request')
        ->name('purchase-requests.reject');
    Route::patch('purchase-requests/{purchase_request}/cancel', [PurchaseRequestController::class, 'cancel'])
        ->middleware('can:update,purchase_request')
        ->name('purchase-requests.cancel');
    Route::get('purchase-requests/{purchase_request}/print', [PurchaseRequestController::class, 'print'])
        ->middleware('can:print,purchase_request')
        ->name('purchase-requests.print');

    Route::resource('purchase-requests', PurchaseRequestController::class)
        ->parameters(['purchase-requests' => 'purchase_request'])
        ->middlewareFor('index', 'can:viewAny,'.PurchaseRequest::class)
        ->middlewareFor('show', 'can:view,purchase_request')
        ->middlewareFor(['create', 'store'], 'can:create,'.PurchaseRequest::class)
        ->middlewareFor(['edit', 'update'], 'can:update,purchase_request')
        ->middlewareFor('destroy', 'can:delete,purchase_request');

    Route::patch('purchase-orders/{purchase_order}/submit', [PurchaseOrderController::class, 'submit'])
        ->middleware('can:submit,purchase_order')
        ->name('purchase-orders.submit');
    Route::patch('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])
        ->middleware('can:approve,purchase_order')
        ->name('purchase-orders.approve');
    Route::patch('purchase-orders/{purchase_order}/reject', [PurchaseOrderController::class, 'reject'])
        ->middleware('can:reject,purchase_order')
        ->name('purchase-orders.reject');
    Route::patch('purchase-orders/{purchase_order}/send', [PurchaseOrderController::class, 'send'])
        ->middleware('can:update,purchase_order')
        ->name('purchase-orders.send');
    Route::patch('purchase-orders/{purchase_order}/complete', [PurchaseOrderController::class, 'complete'])
        ->middleware('can:update,purchase_order')
        ->name('purchase-orders.complete');
    Route::patch('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->middleware('can:update,purchase_order')
        ->name('purchase-orders.cancel');
    Route::get('purchase-orders/{purchase_order}/print', [PurchaseOrderController::class, 'print'])
        ->middleware('can:print,purchase_order')
        ->name('purchase-orders.print');

    Route::resource('purchase-orders', PurchaseOrderController::class)
        ->parameters(['purchase-orders' => 'purchase_order'])
        ->middlewareFor('index', 'can:viewAny,'.PurchaseOrder::class)
        ->middlewareFor('show', 'can:view,purchase_order')
        ->middlewareFor(['create', 'store'], 'can:create,'.PurchaseOrder::class)
        ->middlewareFor(['edit', 'update'], 'can:update,purchase_order')
        ->middlewareFor('destroy', 'can:delete,purchase_order');

    Route::get('receiving-receipts/{receiving_receipt}/print', [ReceivingReceiptController::class, 'print'])
        ->middleware('can:print,receiving_receipt')
        ->name('receiving-receipts.print');

    Route::resource('receiving-receipts', ReceivingReceiptController::class)
        ->parameters(['receiving-receipts' => 'receiving_receipt'])
        ->middlewareFor('index', 'can:viewAny,'.ReceivingReceipt::class)
        ->middlewareFor('show', 'can:view,receiving_receipt')
        ->middlewareFor(['create', 'store'], 'can:create,'.ReceivingReceipt::class)
        ->middlewareFor(['edit', 'update'], 'can:update,receiving_receipt')
        ->middlewareFor('destroy', 'can:delete,receiving_receipt');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::patch('users/{user}/activate', [UserController::class, 'activate'])
            ->middleware('can:users.edit')
            ->name('users.activate');
        Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])
            ->middleware('can:users.edit')
            ->name('users.deactivate');
        Route::post('users/{user}/password-reset-link', [UserController::class, 'sendPasswordResetLink'])
            ->middleware('can:users.edit')
            ->name('users.password-reset-link');

        Route::resource('users', UserController::class)
            ->middlewareFor(['index', 'show'], 'can:users.view')
            ->middlewareFor(['create', 'store'], 'can:users.create')
            ->middlewareFor(['edit', 'update'], 'can:users.edit')
            ->middlewareFor('destroy', 'can:users.delete');

        Route::resource('roles', RoleController::class)
            ->middlewareFor(['index', 'show'], 'can:roles.view')
            ->middlewareFor(['create', 'store'], 'can:roles.create')
            ->middlewareFor(['edit', 'update'], 'can:roles.edit')
            ->middlewareFor('destroy', 'can:roles.delete');
    });
});

require __DIR__.'/settings.php';
