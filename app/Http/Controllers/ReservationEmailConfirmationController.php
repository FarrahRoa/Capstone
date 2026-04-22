<?php

namespace App\Http\Controllers;

use App\Mail\ReservationPendingApprovalAdminMail;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ReservationEmailConfirmationController extends Controller
{
    public function __invoke(Request $request)
    {
        $token = (string) $request->query('token', '');

        $success = false;
        $message = 'Invalid or expired confirmation link.';

        if ($token && strlen($token) === 64) {
            $reservation = Reservation::query()
                ->where('verification_token', $token)
                ->where('status', Reservation::STATUS_EMAIL_VERIFICATION_PENDING)
                ->first();

            if ($reservation) {
                if ($reservation->verification_expires_at && $reservation->verification_expires_at->isPast()) {
                    if ($reservation->canTransitionTo(Reservation::STATUS_REJECTED)) {
                        $reservation->update(['status' => Reservation::STATUS_REJECTED]);
                    }
                    $success = false;
                    $message = 'Confirmation link has expired.';
                } elseif (! $reservation->canTransitionTo(Reservation::STATUS_PENDING_APPROVAL)) {
                    $success = false;
                    $message = 'Invalid or expired confirmation link.';
                } else {
                    $reservation->update([
                        'status' => Reservation::STATUS_PENDING_APPROVAL,
                        'verified_at' => now(),
                        'verification_token' => null,
                        'verification_expires_at' => null,
                    ]);

                    $reservation->load('space', 'user');
                    $admins = User::whereHas('role', function ($q) {
                        $q->where('slug', 'admin');
                    })->get();

                    if ($admins->isNotEmpty()) {
                        foreach ($admins as $admin) {
                            Mail::to($admin->email)->send(new ReservationPendingApprovalAdminMail($reservation));
                        }
                    }

                    $success = true;
                    $message = 'Reservation confirmed. It is now pending admin approval.';
                }
            }
        }

        return response()
            ->view('confirm-reservation', [
                'success' => $success,
                'message' => $message,
            ])
            ->header('Cache-Control', 'no-store, private, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}

