<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReservationEmailConfirmationController;

Route::get('/confirm-reservation', ReservationEmailConfirmationController::class)->name('reservations.confirm');

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
