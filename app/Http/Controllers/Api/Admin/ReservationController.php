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
use App\Models\Space;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'string', Rule::in(Reservation::allowedStatuses())],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $query = Reservation::with(['user', 'space', 'approver', 'logs.actor'])->latest();
        if ($request->filled('status')) {
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
        $reservation->load(['user', 'space', 'approver', 'logs.actor']);

        return ApiResponse::data($reservation);
    }

    /**
     * Confab rooms that are free for this reservation's window (pending pool requests only).
     */
    public function assignableConfabSpaces(Reservation $reservation): JsonResponse
    {
        $reservation->loadMissing('space');
        if (! $reservation->space?->isConfabAssignmentPool()
            || $reservation->status !== Reservation::STATUS_PENDING_APPROVAL) {
            return ApiResponse::data([]);
        }

        $rooms = Space::query()
            ->where('type', Space::TYPE_CONFAB)
            ->where('is_confab_pool', false)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type']);

        $free = $rooms->filter(fn (Space $s) => ! Reservation::conflictsExist(
            (int) $s->id,
            $reservation->start_at,
            $reservation->end_at,
            $reservation->id
        ))->values();

        return ApiResponse::data($free);
    }

    public function approve(ApproveReservationRequest $request, Reservation $reservation): JsonResponse
    {
        if (! $reservation->canTransitionTo(Reservation::STATUS_APPROVED)) {
            return response()->json(['message' => 'Reservation is not pending approval.'], 422);
        }

        $reservation->loadMissing('space');
        $notes = $request->input('notes');
        $assignedId = (int) $request->input('assigned_space_id', 0);

        if ($reservation->space?->isConfabAssignmentPool()) {
            if ($assignedId <= 0) {
                return response()->json([
                    'message' => 'Choose a specific confab room before approving.',
                    'errors' => ['assigned_space_id' => ['Select a free confab room for this time slot.']],
                ], 422);
            }

            $target = Space::query()
                ->whereKey($assignedId)
                ->where('type', Space::TYPE_CONFAB)
                ->where('is_confab_pool', false)
                ->where('is_active', true)
                ->first();

            if (! $target) {
                return response()->json([
                    'message' => 'Invalid confab room selection.',
                    'errors' => ['assigned_space_id' => ['Not an assignable confab room.']],
                ], 422);
            }

            try {
                DB::transaction(function () use ($reservation, $assignedId, $request, $notes, $target) {
                    Space::whereKey($assignedId)->lockForUpdate()->first();

                    if (Reservation::conflictsExist(
                        $assignedId,
                        $reservation->start_at,
                        $reservation->end_at,
                        $reservation->id
                    )) {
                        throw ValidationException::withMessages([
                            'assigned_space_id' => ['That confab room is already booked for this slot.'],
                        ]);
                    }

                    $reservationNumber = 'RES-' . strtoupper(Str::random(8));
                    $logNotes = trim(($notes ? $notes.' ' : '').'Assigned room: '.$target->name);

                    $reservation->update([
                        'space_id' => $assignedId,
                        'status' => Reservation::STATUS_APPROVED,
                        'reservation_number' => $reservationNumber,
                        'approved_by' => $request->user()->id,
                        'approved_at' => now(),
                    ]);

                    ReservationLog::create([
                        'reservation_id' => $reservation->id,
                        'actor_user_id' => $request->user()->id,
                        'actor_type' => ReservationLog::ACTOR_ADMIN,
                        'action' => ReservationLog::ACTION_APPROVE,
                        'notes' => $logNotes !== '' ? $logNotes : null,
                    ]);
                });
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        } else {
            $reservationNumber = 'RES-' . strtoupper(Str::random(8));
            $reservation->update([
                'status' => Reservation::STATUS_APPROVED,
                'reservation_number' => $reservationNumber,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            ReservationLog::create([
                'reservation_id' => $reservation->id,
                'actor_user_id' => $request->user()->id,
                'actor_type' => ReservationLog::ACTOR_ADMIN,
                'action' => ReservationLog::ACTION_APPROVE,
                'notes' => $notes,
            ]);
        }

        $fresh = $reservation->fresh(['user', 'space', 'approver']);
        \Illuminate\Support\Facades\Mail::to($fresh->user->email)
            ->send(new ReservationApprovedMail($fresh));

        return ApiResponse::message(
            'Reservation approved.',
            $fresh
        );
    }

    public function reject(RejectReservationRequest $request, Reservation $reservation): JsonResponse
    {
        if (! $reservation->canTransitionTo(Reservation::STATUS_REJECTED)) {
            return response()->json(['message' => 'Reservation cannot be rejected.'], 422);
        }
        $reservation->update([
            'status' => Reservation::STATUS_REJECTED,
            'rejected_reason' => $request->input('reason'),
        ]);
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'actor_user_id' => $request->user()->id,
            'actor_type' => ReservationLog::ACTOR_ADMIN,
            'action' => ReservationLog::ACTION_REJECT,
            'notes' => $request->input('reason'),
        ]);
        \Illuminate\Support\Facades\Mail::to($reservation->user->email)
            ->send(new ReservationRejectedMail($reservation->fresh('space'), $request->input('reason')));

        return ApiResponse::message(
            'Reservation rejected.',
            $reservation->fresh(['user', 'space'])
        );
    }

    public function cancel(CancelReservationRequest $request, Reservation $reservation): JsonResponse
    {
        if (! $reservation->canTransitionTo(Reservation::STATUS_CANCELLED)) {
            return response()->json(['message' => 'Already cancelled.'], 422);
        }
        $reservation->update(['status' => Reservation::STATUS_CANCELLED]);
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'actor_user_id' => $request->user()->id,
            'actor_type' => ReservationLog::ACTOR_ADMIN,
            'action' => ReservationLog::ACTION_CANCEL,
            'notes' => $request->input('notes'),
        ]);
        \Illuminate\Support\Facades\Mail::to($reservation->user->email)
            ->send(new ReservationRejectedMail($reservation->fresh('space'), $request->input('notes', 'Your reservation was cancelled by admin.')));

        return ApiResponse::message(
            'Reservation cancelled.',
            $reservation->fresh(['user', 'space'])
        );
    }

    public function override(Request $request, Reservation $reservation): JsonResponse
    {
        if (! $reservation->canTransitionTo(Reservation::STATUS_APPROVED)) {
            return response()->json(['message' => 'Reservation is not pending approval.'], 422);
        }

        $request->validate([
            'notes' => 'nullable|string|max:500',
            'assigned_space_id' => 'nullable|integer|exists:spaces,id',
        ]);

        $reservation->loadMissing('space');
        $notes = $request->input('notes');
        $assignedId = (int) $request->input('assigned_space_id', 0);

        if ($reservation->space?->isConfabAssignmentPool()) {
            if ($assignedId <= 0) {
                return response()->json([
                    'message' => 'Choose a specific confab room before approving.',
                    'errors' => ['assigned_space_id' => ['Select a free confab room for this time slot.']],
                ], 422);
            }

            $target = Space::query()
                ->whereKey($assignedId)
                ->where('type', Space::TYPE_CONFAB)
                ->where('is_confab_pool', false)
                ->where('is_active', true)
                ->first();

            if (! $target) {
                return response()->json([
                    'message' => 'Invalid confab room selection.',
                    'errors' => ['assigned_space_id' => ['Not an assignable confab room.']],
                ], 422);
            }

            try {
                DB::transaction(function () use ($reservation, $assignedId, $request, $notes, $target) {
                    Space::whereKey($assignedId)->lockForUpdate()->first();

                    if (Reservation::conflictsExist(
                        $assignedId,
                        $reservation->start_at,
                        $reservation->end_at,
                        $reservation->id
                    )) {
                        throw ValidationException::withMessages([
                            'assigned_space_id' => ['That confab room is already booked for this slot.'],
                        ]);
                    }

                    $reservationNumber = $reservation->reservation_number ?: ('RES-' . strtoupper(Str::random(8)));
                    $logNotes = trim(($notes ? $notes.' ' : '').'Assigned room: '.$target->name);

                    $reservation->update([
                        'space_id' => $assignedId,
                        'status' => Reservation::STATUS_APPROVED,
                        'reservation_number' => $reservationNumber,
                        'approved_by' => $request->user()->id,
                        'approved_at' => now(),
                    ]);

                    ReservationLog::create([
                        'reservation_id' => $reservation->id,
                        'actor_user_id' => $request->user()->id,
                        'actor_type' => ReservationLog::ACTOR_ADMIN,
                        'action' => ReservationLog::ACTION_OVERRIDE,
                        'notes' => $logNotes !== '' ? $logNotes : null,
                    ]);
                });
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        } else {
            $reservationNumber = $reservation->reservation_number ?: ('RES-' . strtoupper(Str::random(8)));
            $reservation->update([
                'status' => Reservation::STATUS_APPROVED,
                'reservation_number' => $reservationNumber,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            ReservationLog::create([
                'reservation_id' => $reservation->id,
                'actor_user_id' => $request->user()->id,
                'actor_type' => ReservationLog::ACTOR_ADMIN,
                'action' => ReservationLog::ACTION_OVERRIDE,
                'notes' => $notes,
            ]);
        }

        $fresh = $reservation->fresh(['user', 'space', 'approver']);
        \Illuminate\Support\Facades\Mail::to($fresh->user->email)
            ->send(new ReservationApprovedMail($fresh));

        return ApiResponse::message(
            'Override applied.',
            $fresh
        );
    }
}
