<?php

namespace App\Http\Controllers;

use App\Mail\Reservation\ReservationPendingApprovalAdminMail;
use App\Mail\Reservation\ReservationUserDeanApprovedMail;
use App\Mail\Reservation\ReservationUserDeanRejectedMail;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Support\ReservationDeanRouting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class DeanReservationActionController extends Controller
{
    /**
     * Signed GET: review only. Does not change reservation state.
     */
    public function review(Request $request, Reservation $reservation)
    {
        $reservation->loadMissing(['space', 'user']);

        $expiry = now()->addDays(21);
        $id = $reservation->id;

        if ($reservation->status !== Reservation::STATUS_PENDING_DEAN_APPROVAL) {
            return response()
                ->view('dean.reservation-result', [
                    'success' => true,
                    'title' => 'No action needed',
                    'message' => 'This reservation is not awaiting dean/office approval. It may have already been decided or moved to the next step.',
                    'reservation' => $reservation,
                ])
                ->header('Cache-Control', 'no-store, private, max-age=0');
        }

        return response()
            ->view('dean.reservation-review', [
                'reservation' => $reservation,
                'approveUrl' => URL::temporarySignedRoute('dean.reservations.approve', $expiry, ['reservation' => $id]),
                'rejectUrl' => URL::temporarySignedRoute('dean.reservations.reject', $expiry, ['reservation' => $id]),
            ])
            ->header('Cache-Control', 'no-store, private, max-age=0');
    }

    /**
     * Signed POST: record dean/office approval (idempotent).
     */
    public function approve(Request $request, Reservation $reservation)
    {
        $applied = false;

        DB::transaction(function () use ($reservation, &$applied) {
            $row = Reservation::whereKey($reservation->id)->lockForUpdate()->first();
            if (! $row || $row->status !== Reservation::STATUS_PENDING_DEAN_APPROVAL) {
                return;
            }
            $applied = true;
            $row->update(['status' => Reservation::STATUS_PENDING_APPROVAL]);
            ReservationLog::create([
                'reservation_id' => $row->id,
                'actor_user_id' => null,
                'actor_type' => ReservationLog::ACTOR_SYSTEM,
                'action' => ReservationLog::ACTION_APPROVE,
                'notes' => 'Dean/office approver approved via secure email action.',
            ]);
        });

        $fresh = $reservation->fresh(['space', 'user']);

        if ($applied) {
            Mail::to($fresh->user->email)->send(new ReservationUserDeanApprovedMail($fresh));
            foreach (ReservationDeanRouting::staffQueueApproverEmails($fresh) as $email) {
                Mail::to($email)->send(new ReservationPendingApprovalAdminMail($fresh));
            }

            return response()
                ->view('dean.reservation-result', [
                    'success' => true,
                    'title' => 'Approval recorded',
                    'message' => 'Thank you. The requester has been notified that you approved this reservation. Library staff will complete the final review.',
                    'reservation' => $fresh,
                ])
                ->header('Cache-Control', 'no-store, private, max-age=0');
        }

        return response()
            ->view('dean.reservation-result', [
                'success' => true,
                'title' => 'Already processed',
                'message' => 'This reservation was already decided or is no longer awaiting dean/office approval. No changes were made.',
                'reservation' => $fresh,
            ])
            ->header('Cache-Control', 'no-store, private, max-age=0');
    }

    /**
     * Signed POST: record dean/office rejection (idempotent).
     */
    public function reject(Request $request, Reservation $reservation)
    {
        $applied = false;

        DB::transaction(function () use ($reservation, &$applied) {
            $row = Reservation::whereKey($reservation->id)->lockForUpdate()->first();
            if (! $row || $row->status !== Reservation::STATUS_PENDING_DEAN_APPROVAL) {
                return;
            }
            $applied = true;
            $row->update([
                'status' => Reservation::STATUS_REJECTED,
                'rejected_reason' => 'Declined by dean/office approver.',
            ]);
            ReservationLog::create([
                'reservation_id' => $row->id,
                'actor_user_id' => null,
                'actor_type' => ReservationLog::ACTOR_SYSTEM,
                'action' => ReservationLog::ACTION_REJECT,
                'notes' => 'Dean/office approver rejected via secure email action.',
            ]);
        });

        $fresh = $reservation->fresh(['space', 'user']);

        if ($applied) {
            Mail::to($fresh->user->email)->send(new ReservationUserDeanRejectedMail($fresh));

            return response()
                ->view('dean.reservation-result', [
                    'success' => true,
                    'title' => 'Rejection recorded',
                    'message' => 'Thank you. The requester has been notified that this reservation was not approved at the dean/office step.',
                    'reservation' => $fresh,
                ])
                ->header('Cache-Control', 'no-store, private, max-age=0');
        }

        return response()
            ->view('dean.reservation-result', [
                'success' => true,
                'title' => 'Already processed',
                'message' => 'This reservation was already decided or is no longer awaiting dean/office approval. No changes were made.',
                'reservation' => $fresh,
            ])
            ->header('Cache-Control', 'no-store, private, max-age=0');
    }
}
