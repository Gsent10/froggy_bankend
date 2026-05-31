<?php

use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\ActivityController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\TransactionController;
use App\Http\Controllers\Customer\WalletController;
use Illuminate\Support\Facades\Route;

Route::name('customer.')->group(function () {

    // Public Auth APIs
    Route::post('register',        [AuthController::class, 'register'])->name('register');
    Route::post('verify-otp',      [AuthController::class, 'verifyOtp'])->name('verify-otp');
    Route::post('resend-otp',      [AuthController::class, 'resendOtp'])->name('resend-otp');
    Route::post('login',           [AuthController::class, 'login'])->name('login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');

    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('reset-password',  [AuthController::class, 'resetPassword'])->name('reset-password');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        // Dashboard
        Route::get('/dashboard',         [DashboardController::class, 'index']);
        Route::get('/dashboard/wallets', [DashboardController::class, 'wallets']);

        Route::prefix('wallets')->group(function () {
            Route::get('/', [WalletController::class, 'index']);
            Route::post('/', [WalletController::class, 'create']);
            Route::post('/activity', [ActivityController::class, 'index']);
            Route::get('/{id}', [WalletController::class, 'show']);
        });

        // Transactions
        Route::get('/wallet/transactions', [TransactionController::class, 'index']);
        Route::post('/wallet/topup',       [TransactionController::class, 'topUp']);
    });
});
