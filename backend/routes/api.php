<?php

use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerJobController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MeasurementController;
use App\Http\Controllers\Api\MeasurementTemplateController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\WhatsAppController;
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
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

Route::post('/billing/webhook', [BillingController::class, 'webhook'])
    ->middleware('throttle:60,1');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::delete('/auth/account', [AuthController::class, 'destroy']);
    Route::put('/business', [BusinessController::class, 'update']);

    Route::get('/billing/plan', [BillingController::class, 'plan']);
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])
        ->middleware('throttle:10,1');
    Route::post('/billing/verify', [BillingController::class, 'verify'])
        ->middleware('throttle:20,1');

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
    Route::get('/customers/{customer}/whatsapp-templates', [WhatsAppController::class, 'templates']);

    Route::get('/measurement-templates', [MeasurementTemplateController::class, 'index']);
    Route::put('/measurement-templates/{slug}/fields', [MeasurementTemplateController::class, 'updateFields']);
    Route::get('/customers/{customer}/measurements', [MeasurementController::class, 'index']);
    Route::post('/customers/{customer}/measurements', [MeasurementController::class, 'store']);
    Route::get('/customers/{customer}/measurements/{measurement}', [MeasurementController::class, 'show']);

    Route::get('/customers/{customer}/jobs', [CustomerJobController::class, 'index']);
    Route::post('/customers/{customer}/jobs', [CustomerJobController::class, 'store']);
    Route::get('/customers/{customer}/jobs/{job}', [CustomerJobController::class, 'show']);
    Route::delete('/customers/{customer}/jobs/{job}', [CustomerJobController::class, 'destroy']);

    Route::get('/customers/{customer}/payments', [PaymentController::class, 'index']);
    Route::post('/customers/{customer}/payments', [PaymentController::class, 'store']);
    Route::get('/customers/{customer}/payments/{payment}/receipt', [ReceiptController::class, 'show']);
    Route::post('/customers/{customer}/payments/{payment}/receipt/email', [ReceiptController::class, 'email']);
    Route::delete('/customers/{customer}/payments/{payment}', [PaymentController::class, 'destroy']);

    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index']);
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

    Route::get('/finance/summary', [FinanceController::class, 'summary']);
    Route::get('/home', [HomeController::class, 'show']);
    Route::get('/jobs', [HomeController::class, 'jobs']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/shops', [AdminShopController::class, 'index']);
        Route::post('/shops/{user}/suspend', [AdminShopController::class, 'suspend']);
        Route::post('/shops/{user}/unsuspend', [AdminShopController::class, 'unsuspend']);
        Route::put('/shops/{user}/subscription', [AdminShopController::class, 'updateSubscription']);
        Route::put('/shops/{user}/password', [AdminShopController::class, 'resetPassword']);
        Route::get('/shops/{user}/payments', [AdminPaymentController::class, 'forShop']);
        Route::get('/payments', [AdminPaymentController::class, 'index']);
    });
});
