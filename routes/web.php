<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CurrentCompanyController;
use App\Http\Controllers\ProductServiceController;
use App\Http\Controllers\VendorController;
use App\Models\Client;
use App\Models\Company;
use App\Models\ProductService;
use App\Models\Vendor;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')
        ->middleware('can:dashboard.view')
        ->name('dashboard');

    Route::patch('current-company/{company}', [CurrentCompanyController::class, 'update'])
        ->name('current-company.update');

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

    Route::resource('product-services', ProductServiceController::class)
        ->except('destroy')
        ->middlewareFor('index', 'can:viewAny,'.ProductService::class)
        ->middlewareFor('show', 'can:view,product_service')
        ->middlewareFor(['create', 'store'], 'can:create,'.ProductService::class)
        ->middlewareFor(['edit', 'update'], 'can:update,product_service');

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
