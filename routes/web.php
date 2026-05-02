<?php

use App\Http\Controllers\DeanReservationActionController;
use App\Http\Controllers\ReservationEmailConfirmationController;
use Illuminate\Support\Facades\Route;

Route::get('/confirm-reservation', ReservationEmailConfirmationController::class)->name('reservations.confirm');

Route::middleware(['signed'])->group(function () {
    Route::get('/dean/reservations/{reservation}/review', [DeanReservationActionController::class, 'review'])
        ->name('dean.reservations.review');
    Route::post('/dean/reservations/{reservation}/approve', [DeanReservationActionController::class, 'approve'])
        ->name('dean.reservations.approve');
    Route::post('/dean/reservations/{reservation}/reject', [DeanReservationActionController::class, 'reject'])
        ->name('dean.reservations.reject');
});

Route::get('/admin/login', function () {
    return view('admin-login');
})->name('admin.login');

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
