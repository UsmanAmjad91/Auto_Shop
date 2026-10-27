<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\PartApiController;
use App\Http\Controllers\Api\ServiceJobApiController;
use App\Http\Controllers\Api\VehicleApiController;
use Illuminate\Support\Facades\Route;

/*
| REST API v1 — authenticate with a Sanctum bearer token
| (POST /api/v1/auth/token, or create one under Profile > Security).
| Every endpoint enforces the same permissions as the web UI.
*/
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::post('auth/token', [AuthTokenController::class, 'store'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'approved'])->group(function () {
        Route::get('me', [AuthTokenController::class, 'me']);
        Route::delete('auth/token', [AuthTokenController::class, 'destroy']);

        Route::get('dashboard', [DashboardApiController::class, 'index'])->middleware('permission:dashboard.view');
        Route::post('vin/decode', [VehicleApiController::class, 'decodeVin'])->middleware(['permission:vehicles.create|vehicles.edit', 'throttle:vin']);

        Route::get('vehicles', [VehicleApiController::class, 'index'])->middleware('permission:vehicles.view');
        Route::post('vehicles', [VehicleApiController::class, 'store'])->middleware('permission:vehicles.create');
        Route::get('vehicles/{vehicle}', [VehicleApiController::class, 'show'])->middleware('permission:vehicles.view');
        Route::put('vehicles/{vehicle}', [VehicleApiController::class, 'update'])->middleware('permission:vehicles.edit');
        Route::delete('vehicles/{vehicle}', [VehicleApiController::class, 'destroy'])->middleware('permission:vehicles.delete');

        Route::get('parts', [PartApiController::class, 'index'])->middleware('permission:parts.view');
        Route::get('parts/{part}', [PartApiController::class, 'show'])->middleware('permission:parts.view');
        Route::post('parts/{part}/stock', [PartApiController::class, 'stock'])->middleware('permission:parts.adjust_stock');

        Route::get('customers', [CustomerApiController::class, 'index'])->middleware('permission:customers.view');
        Route::post('customers', [CustomerApiController::class, 'store'])->middleware('permission:customers.create');
        Route::get('customers/{customer}', [CustomerApiController::class, 'show'])->middleware('permission:customers.view');

        Route::get('service-jobs', [ServiceJobApiController::class, 'index'])->middleware('permission:service_jobs.view');
        Route::get('service-jobs/{job}', [ServiceJobApiController::class, 'show'])->middleware('permission:service_jobs.view');
        Route::post('service-jobs/{job}/status', [ServiceJobApiController::class, 'status'])->middleware('permission:service_jobs.edit');

        Route::get('invoices', [InvoiceApiController::class, 'index'])->middleware('permission:invoices.view');
        Route::get('invoices/{invoice}', [InvoiceApiController::class, 'show'])->middleware('permission:invoices.view');
        Route::post('invoices/{invoice}/payments', [InvoiceApiController::class, 'pay'])->middleware('permission:payments.create');
    });
});
