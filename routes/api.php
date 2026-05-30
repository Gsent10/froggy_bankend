<?php

use App\Http\Controllers\Customer\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->name('customer.')->group(function () {

    // Public
    Route::post('register',        [AuthController::class, 'register'])->name('register');
    Route::post('verify-otp',      [AuthController::class, 'verifyOtp'])->name('verify-otp');
    Route::post('resend-otp',      [AuthController::class, 'resendOtp'])->name('resend-otp');
    Route::post('login',           [AuthController::class, 'login'])->name('login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');

    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('reset-password',  [AuthController::class, 'resetPassword'])->name('reset-password');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});
