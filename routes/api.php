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
    Route::post('/me/google-profile', [App\Http\Controllers\Api\GoogleProfileController::class, 'store']);

    Route::get('/reservation-guidelines', [App\Http\Controllers\Api\ReservationGuidelinesController::class, 'show']);

    Route::get('/reservations', [App\Http\Controllers\Api\ReservationController::class, 'index']);
    Route::get('/reservations/{reservation}', [App\Http\Controllers\Api\ReservationController::class, 'show']);
    Route::post('/reservations', [App\Http\Controllers\Api\ReservationController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:reservation.view_all'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reservations', [App\Http\Controllers\Api\Admin\ReservationController::class, 'index']);
    Route::get('/reservations/{reservation}', [App\Http\Controllers\Api\Admin\ReservationController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'permission:reservation.approve'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/reservations/{reservation}/approve', [App\Http\Controllers\Api\Admin\ReservationController::class, 'approve']);
});

Route::middleware(['auth:sanctum', 'permission:reservation.reject'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/reservations/{reservation}/reject', [App\Http\Controllers\Api\Admin\ReservationController::class, 'reject']);
});

Route::middleware(['auth:sanctum', 'permission:reservation.override'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/reservations/{reservation}/cancel', [App\Http\Controllers\Api\Admin\ReservationController::class, 'cancel']);
    Route::post('/reservations/{reservation}/override', [App\Http\Controllers\Api\Admin\ReservationController::class, 'override']);
});

Route::middleware(['auth:sanctum', 'permission:spaces.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/spaces', [App\Http\Controllers\Api\Admin\SpaceController::class, 'index']);
    Route::post('/spaces', [App\Http\Controllers\Api\Admin\SpaceController::class, 'store']);
    Route::put('/spaces/{space}', [App\Http\Controllers\Api\Admin\SpaceController::class, 'update']);
    Route::post('/spaces/{space}/toggle-active', [App\Http\Controllers\Api\Admin\SpaceController::class, 'toggleActive']);
});

Route::middleware(['auth:sanctum', 'permission:users.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [App\Http\Controllers\Api\Admin\UserController::class, 'index']);
    Route::get('/roles', [App\Http\Controllers\Api\Admin\UserController::class, 'roles']);
    Route::patch('/users/{user}', [App\Http\Controllers\Api\Admin\UserController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'permission:reports.view'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reports', [App\Http\Controllers\Api\Admin\ReportController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'permission:reports.export'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reports/export', [App\Http\Controllers\Api\Admin\ReportController::class, 'export']);
});

Route::middleware(['auth:sanctum', 'permission:policies.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/policies/reservation-guidelines', [App\Http\Controllers\Api\Admin\PolicyController::class, 'showReservationGuidelines']);
    Route::put('/policies/reservation-guidelines', [App\Http\Controllers\Api\Admin\PolicyController::class, 'updateReservationGuidelines']);
});
