<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerJobController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\MeasurementController;
use App\Http\Controllers\Api\MeasurementTemplateController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
});

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);

    Route::get('/measurement-templates', [MeasurementTemplateController::class, 'index']);
    Route::get('/customers/{customer}/measurements', [MeasurementController::class, 'index']);
    Route::post('/customers/{customer}/measurements', [MeasurementController::class, 'store']);
    Route::get('/customers/{customer}/measurements/{measurement}', [MeasurementController::class, 'show']);

    Route::get('/customers/{customer}/jobs', [CustomerJobController::class, 'index']);
    Route::post('/customers/{customer}/jobs', [CustomerJobController::class, 'store']);
    Route::get('/customers/{customer}/jobs/{job}', [CustomerJobController::class, 'show']);
    Route::delete('/customers/{customer}/jobs/{job}', [CustomerJobController::class, 'destroy']);

    Route::get('/customers/{customer}/payments', [PaymentController::class, 'index']);
    Route::post('/customers/{customer}/payments', [PaymentController::class, 'store']);
    Route::delete('/customers/{customer}/payments/{payment}', [PaymentController::class, 'destroy']);

    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index']);
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
});
