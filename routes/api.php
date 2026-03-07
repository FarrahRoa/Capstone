<?php

use Illuminate\Support\Facades\Route;

Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
Route::post('/otp/verify', [App\Http\Controllers\Api\AuthController::class, 'verifyOtp']);
Route::post('/otp/resend', [App\Http\Controllers\Api\AuthController::class, 'resendOtp']);

Route::get('/spaces', [App\Http\Controllers\Api\SpaceController::class, 'index']);
Route::get('/availability', [App\Http\Controllers\Api\AvailabilityController::class, 'index']);

Route::post('/reservations/confirm-email', [App\Http\Controllers\Api\ReservationController::class, 'confirmEmail']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [App\Http\Controllers\Api\AuthController::class, 'me']);
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);

    Route::get('/reservations', [App\Http\Controllers\Api\ReservationController::class, 'index']);
    Route::get('/reservations/{reservation}', [App\Http\Controllers\Api\ReservationController::class, 'show']);
    Route::post('/reservations', [App\Http\Controllers\Api\ReservationController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reservations', [App\Http\Controllers\Api\Admin\ReservationController::class, 'index']);
    Route::get('/reservations/{reservation}', [App\Http\Controllers\Api\Admin\ReservationController::class, 'show']);
    Route::post('/reservations/{reservation}/approve', [App\Http\Controllers\Api\Admin\ReservationController::class, 'approve']);
    Route::post('/reservations/{reservation}/reject', [App\Http\Controllers\Api\Admin\ReservationController::class, 'reject']);
    Route::post('/reservations/{reservation}/cancel', [App\Http\Controllers\Api\Admin\ReservationController::class, 'cancel']);
    Route::post('/reservations/{reservation}/override', [App\Http\Controllers\Api\Admin\ReservationController::class, 'override']);

    Route::get('/reports', [App\Http\Controllers\Api\Admin\ReportController::class, 'index']);
    Route::get('/reports/export', [App\Http\Controllers\Api\Admin\ReportController::class, 'export']);
});
