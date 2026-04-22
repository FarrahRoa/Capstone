<?php

use Illuminate\Support\Facades\Route;

Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login'])->middleware('throttle:otp-login');
Route::post('/admin/login', [App\Http\Controllers\Api\AuthController::class, 'adminLogin'])->middleware('throttle:otp-login');
Route::post('/otp/verify', [App\Http\Controllers\Api\AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify');
Route::post('/otp/resend', [App\Http\Controllers\Api\AuthController::class, 'resendOtp'])->middleware('throttle:otp-resend');

Route::get('/admin/librarian-invite/validate', [App\Http\Controllers\Api\LibrarianInviteController::class, 'validateInvite']);
Route::post('/admin/librarian-invite/accept', [App\Http\Controllers\Api\LibrarianInviteController::class, 'accept']);

Route::get('/spaces', [App\Http\Controllers\Api\SpaceController::class, 'index']);
Route::get('/public/schedule-overview', [App\Http\Controllers\Api\AvailabilityController::class, 'publicScheduleOverview']);
Route::get('/public/availability/month-summary', [App\Http\Controllers\Api\AvailabilityController::class, 'publicMonthSummary']);
Route::get('/public/availability/month-overview', [App\Http\Controllers\Api\AvailabilityController::class, 'publicMonthOverview']);
Route::get('/availability', [App\Http\Controllers\Api\AvailabilityController::class, 'index']);
Route::get('/availability/month-summary', [App\Http\Controllers\Api\AvailabilityController::class, 'monthSummary']);
Route::get('/availability/month-overview', [App\Http\Controllers\Api\AvailabilityController::class, 'monthOverview']);

Route::post('/reservations/confirm-email', [App\Http\Controllers\Api\ReservationController::class, 'confirmEmail']);

Route::middleware(['auth:sanctum', 'token.fresh'])->group(function () {
    Route::get('/me', [App\Http\Controllers\Api\AuthController::class, 'me']);
    Route::post('/me/profile', [App\Http\Controllers\Api\AuthController::class, 'completeProfile']);
    Route::patch('/me/account', [App\Http\Controllers\Api\AuthController::class, 'updateAccount']);
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);

    Route::get('/reservation-guidelines', [App\Http\Controllers\Api\ReservationGuidelinesController::class, 'show']);

    Route::get('/reservations/active-count', [App\Http\Controllers\Api\ReservationController::class, 'activeCount']);
    Route::get('/reservations', [App\Http\Controllers\Api\ReservationController::class, 'index']);
    Route::get('/reservations/{reservation}', [App\Http\Controllers\Api\ReservationController::class, 'show']);
    Route::post('/reservations', [App\Http\Controllers\Api\ReservationController::class, 'store']);
    Route::patch('/reservations/{reservation}', [App\Http\Controllers\Api\ReservationController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'token.fresh', 'permission:reservation.view_all'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reservations', [App\Http\Controllers\Api\Admin\ReservationController::class, 'index']);
    Route::get('/reservations/{reservation}', [App\Http\Controllers\Api\Admin\ReservationController::class, 'show']);
    Route::get('/activity/reservation-logs', [App\Http\Controllers\Api\Admin\DashboardActivityController::class, 'recentReservationLogs']);
});

Route::middleware(['auth:sanctum', 'token.fresh', 'permission:system.cloud_sync'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/cloud-sync/status', [App\Http\Controllers\Api\Admin\CloudSyncController::class, 'status']);
    Route::post('/cloud-sync/upload', [App\Http\Controllers\Api\Admin\CloudSyncController::class, 'upload']);
});

Route::middleware(['auth:sanctum', 'token.fresh', 'permission:reservation.approve'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reservations/{reservation}/assignable-confab-spaces', [App\Http\Controllers\Api\Admin\ReservationController::class, 'assignableConfabSpaces']);
    Route::post('/reservations/{reservation}/approve', [App\Http\Controllers\Api\Admin\ReservationController::class, 'approve']);
});

Route::middleware(['auth:sanctum', 'token.fresh', 'permission:reservation.reject'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/reservations/{reservation}/reject', [App\Http\Controllers\Api\Admin\ReservationController::class, 'reject']);
});

Route::middleware(['auth:sanctum', 'token.fresh', 'permission:reservation.override'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/reservations/{reservation}/cancel', [App\Http\Controllers\Api\Admin\ReservationController::class, 'cancel']);
    Route::post('/reservations/{reservation}/override', [App\Http\Controllers\Api\Admin\ReservationController::class, 'override']);
});

Route::middleware(['auth:sanctum', 'token.fresh', 'permission:spaces.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/spaces', [App\Http\Controllers\Api\Admin\SpaceController::class, 'index']);
    Route::post('/spaces', [App\Http\Controllers\Api\Admin\SpaceController::class, 'store']);
    Route::put('/spaces/{space}', [App\Http\Controllers\Api\Admin\SpaceController::class, 'update']);
    Route::post('/spaces/{space}/toggle-active', [App\Http\Controllers\Api\Admin\SpaceController::class, 'toggleActive']);
});

Route::middleware(['auth:sanctum', 'token.fresh', 'permission:users.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [App\Http\Controllers\Api\Admin\UserController::class, 'index']);
    Route::get('/roles', [App\Http\Controllers\Api\Admin\UserController::class, 'roles']);
    Route::patch('/users/{user}', [App\Http\Controllers\Api\Admin\UserController::class, 'update']);
    Route::post('/users/invite-portal-role', [App\Http\Controllers\Api\Admin\UserController::class, 'invitePortalRole']);
});

Route::middleware(['auth:sanctum', 'token.fresh', 'permission:reports.view'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reports', [App\Http\Controllers\Api\Admin\ReportController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'token.fresh', 'permission:reports.export'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reports/export', [App\Http\Controllers\Api\Admin\ReportController::class, 'export']);
});

Route::middleware(['auth:sanctum', 'token.fresh', 'permission:policies.manage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/policies/reservation-guidelines', [App\Http\Controllers\Api\Admin\PolicyController::class, 'showReservationGuidelines']);
    Route::put('/policies/reservation-guidelines', [App\Http\Controllers\Api\Admin\PolicyController::class, 'updateReservationGuidelines']);
    Route::get('/policies/operating-hours', [App\Http\Controllers\Api\Admin\PolicyController::class, 'showOperatingHours']);
    Route::put('/policies/operating-hours', [App\Http\Controllers\Api\Admin\PolicyController::class, 'updateOperatingHours']);

    Route::get('/dean-email-mappings', [App\Http\Controllers\Api\Admin\DeanEmailMappingController::class, 'index']);
    Route::post('/dean-email-mappings', [App\Http\Controllers\Api\Admin\DeanEmailMappingController::class, 'store']);
    Route::patch('/dean-email-mappings/{deanEmailMapping}', [App\Http\Controllers\Api\Admin\DeanEmailMappingController::class, 'update']);
    Route::delete('/dean-email-mappings/{deanEmailMapping}', [App\Http\Controllers\Api\Admin\DeanEmailMappingController::class, 'destroy']);
});
