<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Support\ReservationDeanRouting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
                } else {
                    $reservation->load('space', 'user');
                    $confirmBlocked = false;
                    try {
                        ReservationDeanRouting::assertAudienceAndDeanMappingForReservation(
                            $reservation->space,
                            $reservation->user,
                            $reservation->event_request_type
                        );
                    } catch (ValidationException $e) {
                        $confirmBlocked = true;
                        $message = collect($e->errors())->flatten()->first() ?? 'Reservation cannot be confirmed.';
                    }
                    if (! $confirmBlocked) {
                        $nextStatus = ReservationDeanRouting::statusAfterRequesterConfirmsEmail($reservation);
                        if (! $reservation->canTransitionTo($nextStatus)) {
                            $success = false;
                            $message = 'Invalid or expired confirmation link.';
                        } else {
                            $reservation->update([
                                'status' => $nextStatus,
                                'verified_at' => now(),
                                'verification_token' => null,
                                'verification_expires_at' => null,
                            ]);
                            $reservation->load('space', 'user');
                            ReservationDeanRouting::dispatchPostUserVerificationNotifications($reservation->fresh(['space', 'user']));

                            $success = true;
                            $message = $nextStatus === Reservation::STATUS_PENDING_DEAN_APPROVAL
                                ? 'Reservation confirmed. It is now pending dean/office approval.'
                                : 'Reservation confirmed. It is now pending admin approval.';
                        }
                    }
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

