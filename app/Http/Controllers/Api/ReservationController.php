<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReservationVerificationMailException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Http\Requests\Reservation\UpdateReservationRequest;
use App\Mail\Reservation\ReservationVerificationMail;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Space;
use App\Services\ReservationReadableIdService;
use App\Support\ApiResponse;
use App\Support\ReservationDeanRouting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReservationController extends Controller
{
    public function activeCount(Request $request): JsonResponse
    {
        $tz = (string) config('app.timezone');
        $count = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', Reservation::activeUserLimitStatuses())
            ->where('end_at', '>', Carbon::now($tz))
            ->count();

        return ApiResponse::data(['count' => $count]);
    }

    /**
     * Reservations belonging to the authenticated user that overlap a Manila civil date.
     * Used by the booking grid to show "My reservation" blocks without exposing other users' details.
     */
    public function myDay(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'space_id' => 'nullable|exists:spaces,id',
        ]);

        $tz = (string) config('app.timezone');
        $dayStart = Carbon::parse($request->input('date'), $tz)->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay()->addSecond();

        $q = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', Reservation::blockingStatuses())
            ->where('start_at', '<', $dayEnd)
            ->where('end_at', '>', $dayStart)
            ->with(['space', 'approver', 'logs.actor'])
            ->orderBy('start_at');

        if ($request->filled('space_id')) {
            $q->where('space_id', (int) $request->input('space_id'));
        }

        $rows = $q->get()->map(fn (Reservation $r) => $r->toArrayForUserApi())->values()->all();

        return ApiResponse::data($rows);
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->reservations()->with(['space', 'approver', 'logs.actor'])->latest();
        $reservations = $query->paginate(20);
        $reservations->through(fn (Reservation $r) => $r->toArrayForUserApi());

        return response()->json($reservations);
    }

    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        $actor = $request->user();
        $actor->loadMissing('role');
        $isOwner = (int) $reservation->user_id === (int) $actor->id;
        if (! $isOwner && ! $actor->canDo('reservation.view_all')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $reservation->load(['space', 'user', 'approver', 'logs.actor']);

        return ApiResponse::data($reservation->toArrayForUserApi());
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $spaceRowEarly = Space::find((int) $data['space_id']);
        if ($spaceRowEarly && ! ReservationDeanRouting::spaceUsesAvrLobbyAudienceRouting($spaceRowEarly)) {
            unset($data['event_request_type']);
        }
        $data['user_id'] = $request->user()->id;
        $data['status'] = Reservation::initialCreateStatus();
        $data['verification_token'] = Str::random(64);
        $data['verification_expires_at'] = now()->addHours(24);

        try {
            $reservation = DB::transaction(function () use ($data, $request) {
                // Minimal locking strategy: lock the target space row to reduce race window per space_id.
                // On engines that support row locks (e.g. MySQL/Postgres), this serializes reservation creation per space.
                // On SQLite, lockForUpdate is ignored; the transaction still reduces interleaving, but cannot fully prevent races.
                $spaceRow = Space::whereKey($data['space_id'])->lockForUpdate()->first();
                if (! $spaceRow?->isConfabAssignmentPool()) {
                    $conflicting = Reservation::query()
                        ->where('space_id', $data['space_id'])
                        ->blocking()
                        ->overlapping($data['start_at'], $data['end_at'])
                        ->lockForUpdate()
                        ->exists();

                    if ($conflicting) {
                        throw ValidationException::withMessages([
                            'slot' => ['Selected time slot is not available.'],
                        ]);
                    }
                }

                $reservation = Reservation::create($data);

                ReservationLog::create([
                    'reservation_id' => $reservation->id,
                    'actor_user_id' => $reservation->user_id,
                    'actor_type' => ReservationLog::ACTOR_USER,
                    'action' => ReservationLog::ACTION_CREATE,
                    'notes' => null,
                ]);

                $reservation->load('user');
                app(ReservationReadableIdService::class)->assignAfterSuccessfulCreate(
                    $reservation,
                    $spaceRow,
                    $request->user()
                );
                $reservation->refresh();

                // Phase 3 hardening: verification email is part of "successful create".
                // If sending fails, the transaction must roll back so we don't leave an unusable reservation behind.
                $reservation->load('space', 'user');
                try {
                    Mail::to($reservation->user->email)->send(new ReservationVerificationMail($reservation));
                } catch (Throwable $e) {
                    throw new ReservationVerificationMailException(
                        userId: (int) $reservation->user_id,
                        email: (string) $reservation->user->email,
                        spaceId: (int) $reservation->space_id,
                        startAt: (string) $reservation->start_at,
                        endAt: (string) $reservation->end_at,
                        previous: $e,
                    );
                }

                return $reservation;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (ReservationVerificationMailException $e) {
            Log::error('Reservation verification email send failed on create', [
                'user_id' => $e->userId,
                'email' => $e->email,
                'space_id' => $e->spaceId,
                'start_at' => $e->startAt,
                'end_at' => $e->endAt,
                'mailer' => config('mail.default'),
                'exception' => $e->getPrevious() ? $e->getPrevious()::class : $e::class,
                'message' => $e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Reservation could not be completed because we could not send the verification email. Check mail configuration or try again shortly.',
            ], 503);
        }

        return ApiResponse::message(
            'Reservation created. Please confirm your email using the link sent to your XU email.',
            $reservation->loadMissing(['space', 'user', 'approver', 'logs.actor'])->toArrayForUserApi(),
            201
        );
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $editableStatuses = [
            Reservation::STATUS_EMAIL_VERIFICATION_PENDING,
            Reservation::STATUS_PENDING_DEAN_APPROVAL,
            Reservation::STATUS_PENDING_APPROVAL,
            Reservation::STATUS_APPROVED,
            Reservation::STATUS_OVERRIDDEN,
        ];
        if (! in_array($reservation->status, $editableStatuses, true)) {
            return response()->json(['message' => 'Reservation cannot be edited in its current status.'], 422);
        }

        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);
        if ($reservation->end_at && $reservation->end_at->copy()->timezone($tz)->lte($now)) {
            return response()->json(['message' => 'Past reservations cannot be edited.'], 422);
        }

        $data = $request->validated();
        $data['space_id'] = (int) $data['space_id'];

        $nextStatus = match ($reservation->status) {
            Reservation::STATUS_EMAIL_VERIFICATION_PENDING => Reservation::STATUS_EMAIL_VERIFICATION_PENDING,
            Reservation::STATUS_PENDING_DEAN_APPROVAL => Reservation::STATUS_PENDING_DEAN_APPROVAL,
            Reservation::STATUS_OVERRIDDEN => Reservation::STATUS_PENDING_APPROVAL,
            default => Reservation::STATUS_PENDING_APPROVAL,
        };

        try {
            $updated = DB::transaction(function () use ($reservation, $data, $request, $nextStatus) {
                $targetSpace = Space::whereKey($data['space_id'])->lockForUpdate()->first();

                if (! $targetSpace?->isConfabAssignmentPool()) {
                    $conflicting = Reservation::query()
                        ->where('space_id', $data['space_id'])
                        ->where('id', '<>', $reservation->id)
                        ->blocking()
                        ->overlapping($data['start_at'], $data['end_at'])
                        ->lockForUpdate()
                        ->exists();

                    if ($conflicting) {
                        throw ValidationException::withMessages([
                            'slot' => ['Selected time slot is not available.'],
                        ]);
                    }
                }

                $eventAudience = null;
                if (ReservationDeanRouting::spaceUsesAvrLobbyAudienceRouting($targetSpace)) {
                    $eventAudience = array_key_exists('event_request_type', $data)
                        ? $data['event_request_type']
                        : $reservation->event_request_type;
                }

                $reservation->update([
                    'space_id' => $data['space_id'],
                    'start_at' => $data['start_at'],
                    'end_at' => $data['end_at'],
                    'status' => $nextStatus,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejected_reason' => null,
                    'event_request_type' => $eventAudience,
                ]);

                ReservationLog::create([
                    'reservation_id' => $reservation->id,
                    'actor_user_id' => $request->user()->id,
                    'actor_type' => ReservationLog::ACTOR_USER,
                    'action' => ReservationLog::ACTION_UPDATE,
                    'notes' => match ($nextStatus) {
                        Reservation::STATUS_EMAIL_VERIFICATION_PENDING => 'Reservation details updated before email confirmation.',
                        Reservation::STATUS_PENDING_DEAN_APPROVAL => 'Reservation details updated; still pending dean/office approval.',
                        default => 'Reservation details updated; returned to admin review.',
                    },
                ]);

                return $reservation->fresh(['space', 'approver', 'logs.actor']);
            });
        } catch (ValidationException $e) {
            throw $e;
        }

        $userMessage = match ($nextStatus) {
            Reservation::STATUS_EMAIL_VERIFICATION_PENDING => 'Reservation updated. Please confirm your request using the link sent to your XU email if you have not already.',
            Reservation::STATUS_PENDING_DEAN_APPROVAL => 'Reservation updated. It remains pending dean/office approval.',
            default => 'Reservation updated. It is now pending admin approval.',
        };

        return ApiResponse::message(
            $userMessage,
            $updated->loadMissing(['space', 'user', 'approver', 'logs.actor'])->toArrayForUserApi()
        );
    }

    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);
        if ($reservation->end_at && $reservation->end_at->copy()->timezone($tz)->lte($now)) {
            return response()->json(['message' => 'Past reservations cannot be cancelled.'], 422);
        }

        if ($reservation->status === Reservation::STATUS_REJECTED) {
            return response()->json(['message' => 'This reservation cannot be cancelled.'], 422);
        }

        if (! $reservation->canTransitionTo(Reservation::STATUS_CANCELLED)) {
            return response()->json(['message' => 'This reservation cannot be cancelled.'], 422);
        }

        $reservation->update(['status' => Reservation::STATUS_CANCELLED]);

        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'actor_user_id' => $request->user()->id,
            'actor_type' => ReservationLog::ACTOR_USER,
            'action' => ReservationLog::ACTION_CANCEL,
            'notes' => null,
        ]);

        return ApiResponse::message(
            'Reservation cancelled.',
            $reservation->fresh(['space', 'approver', 'logs.actor'])->loadMissing(['user'])->toArrayForUserApi()
        );
    }

    public function confirmEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
        ]);
        $reservation = Reservation::where('verification_token', $request->input('token'))
            ->where('status', Reservation::STATUS_EMAIL_VERIFICATION_PENDING)
            ->first();
        if (!$reservation) {
            return response()->json(['message' => 'Invalid or expired confirmation link.'], 422);
        }
        if ($reservation->verification_expires_at && $reservation->verification_expires_at->isPast()) {
            if (!$reservation->canTransitionTo(Reservation::STATUS_REJECTED)) {
                return response()->json(['message' => 'Invalid or expired confirmation link.'], 422);
            }
            $reservation->update(['status' => Reservation::STATUS_REJECTED]);
            return response()->json(['message' => 'Confirmation link has expired.'], 422);
        }
        $reservation->load('space', 'user');
        try {
            ReservationDeanRouting::assertAudienceAndDeanMappingForReservation(
                $reservation->space,
                $reservation->user,
                $reservation->event_request_type
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Reservation cannot be confirmed.',
                'errors' => $e->errors(),
            ], 422);
        }
        $nextStatus = ReservationDeanRouting::statusAfterRequesterConfirmsEmail($reservation);
        if (! $reservation->canTransitionTo($nextStatus)) {
            return response()->json(['message' => 'Invalid or expired confirmation link.'], 422);
        }
        $reservation->update([
            'status' => $nextStatus,
            'verified_at' => now(),
            'verification_token' => null,
            'verification_expires_at' => null,
        ]);
        $reservation->load('space', 'user');
        ReservationDeanRouting::dispatchPostUserVerificationNotifications($reservation->fresh(['space', 'user']));
        $msg = $nextStatus === Reservation::STATUS_PENDING_DEAN_APPROVAL
            ? 'Reservation confirmed. It is now pending dean/office approval.'
            : 'Reservation confirmed. It is now pending admin approval.';

        return ApiResponse::message(
            $msg,
            $reservation->loadMissing(['space', 'user', 'approver', 'logs.actor'])->toArrayForUserApi()
        );
    }
}
