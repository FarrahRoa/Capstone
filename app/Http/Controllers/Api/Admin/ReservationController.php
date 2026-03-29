<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ApproveReservationRequest;
use App\Http\Requests\Api\Admin\CancelReservationRequest;
use App\Http\Requests\Api\Admin\RejectReservationRequest;
use App\Mail\ReservationApprovedMail;
use App\Mail\ReservationRejectedMail;
use App\Models\Reservation;
use App\Models\ReservationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Reservation::with(['user', 'space', 'approver', 'logs.admin'])->latest();
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('from')) {
            $query->whereDate('start_at', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->whereDate('start_at', '<=', $request->input('to'));
        }
        $reservations = $query->paginate(20);
        return response()->json($reservations);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $reservation->load(['user', 'space', 'approver', 'logs.admin']);
        return response()->json($reservation);
    }

    public function approve(ApproveReservationRequest $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== Reservation::STATUS_PENDING_APPROVAL) {
            return response()->json(['message' => 'Reservation is not pending approval.'], 422);
        }
        $reservationNumber = 'RES-' . strtoupper(Str::random(8));
        $reservation->update([
            'status' => Reservation::STATUS_APPROVED,
            'reservation_number' => $reservationNumber,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'admin_id' => $request->user()->id,
            'action' => ReservationLog::ACTION_APPROVE,
            'notes' => $request->input('notes'),
        ]);
        \Illuminate\Support\Facades\Mail::to($reservation->user->email)
            ->send(new ReservationApprovedMail($reservation->fresh(['space', 'approver'])));
        return response()->json([
            'message' => 'Reservation approved.',
            'reservation' => $reservation->fresh(['user', 'space', 'approver']),
        ]);
    }

    public function reject(RejectReservationRequest $request, Reservation $reservation): JsonResponse
    {
        if (!in_array($reservation->status, [Reservation::STATUS_PENDING_APPROVAL, Reservation::STATUS_EMAIL_VERIFICATION_PENDING])) {
            return response()->json(['message' => 'Reservation cannot be rejected.'], 422);
        }
        $reservation->update([
            'status' => Reservation::STATUS_REJECTED,
            'rejected_reason' => $request->input('reason'),
        ]);
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'admin_id' => $request->user()->id,
            'action' => ReservationLog::ACTION_REJECT,
            'notes' => $request->input('reason'),
        ]);
        \Illuminate\Support\Facades\Mail::to($reservation->user->email)
            ->send(new ReservationRejectedMail($reservation->fresh('space'), $request->input('reason')));
        return response()->json([
            'message' => 'Reservation rejected.',
            'reservation' => $reservation->fresh(['user', 'space']),
        ]);
    }

    public function cancel(CancelReservationRequest $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->status === Reservation::STATUS_CANCELLED) {
            return response()->json(['message' => 'Already cancelled.'], 422);
        }
        $reservation->update(['status' => Reservation::STATUS_CANCELLED]);
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'admin_id' => $request->user()->id,
            'action' => ReservationLog::ACTION_CANCEL,
            'notes' => $request->input('notes'),
        ]);
        \Illuminate\Support\Facades\Mail::to($reservation->user->email)
            ->send(new ReservationRejectedMail($reservation->fresh('space'), $request->input('notes', 'Your reservation was cancelled by admin.')));
        return response()->json([
            'message' => 'Reservation cancelled.',
            'reservation' => $reservation->fresh(['user', 'space']),
        ]);
    }

    public function override(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== Reservation::STATUS_PENDING_APPROVAL) {
            return response()->json(['message' => 'Reservation is not pending approval.'], 422);
        }
        $request->validate(['notes' => 'nullable|string|max:500']);
        $reservationNumber = $reservation->reservation_number ?: ('RES-' . strtoupper(Str::random(8)));
        $reservation->update([
            'status' => Reservation::STATUS_APPROVED,
            'reservation_number' => $reservationNumber,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'admin_id' => $request->user()->id,
            'action' => ReservationLog::ACTION_OVERRIDE,
            'notes' => $request->input('notes'),
        ]);
        \Illuminate\Support\Facades\Mail::to($reservation->user->email)
            ->send(new ReservationApprovedMail($reservation->fresh(['space', 'approver'])));
        return response()->json([
            'message' => 'Override applied.',
            'reservation' => $reservation->fresh(['user', 'space', 'approver']),
        ]);
    }
}
