<?php

namespace App\Services;

use App\Mail\Reservation\ReservationDisplacedByAdminOverrideMail;
use App\Mail\Reservation\ReservationGloballyOverriddenMail;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\ReservationOverrideLog;
use App\Models\Space;
use App\Models\User;
use App\Notifications\DatabaseTextNotification;
use App\Support\ReservationUserPriority;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ReservationGlobalOverrideService
{
    /**
     * @param  array{reason: string, space_id: int, start_at: string, end_at: string}  $payload
     */
    public function apply(User $admin, Reservation $reservation, array $payload): Reservation
    {
        if (! $admin->relationLoaded('role')) {
            $admin->load('role');
        }
        if (! $admin->role || $admin->role->slug !== 'admin') {
            throw new InvalidArgumentException('Only system administrators may execute a global override.');
        }

        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => ['Override reason is required.']]);
        }

        $spaceId = (int) ($payload['space_id'] ?? 0);
        $tz = (string) config('app.timezone');
        $start = Carbon::parse((string) $payload['start_at'], $tz);
        $end = Carbon::parse((string) $payload['end_at'], $tz);

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['end_at' => ['End time must be after start time.']]);
        }

        return DB::transaction(function () use ($admin, $reservation, $reason, $spaceId, $start, $end, $tz) {
            /** @var Reservation $locked */
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing(['space', 'user']);

            if (! $locked->canBeGloballyOverriddenByAdmin()) {
                throw ValidationException::withMessages([
                    'reservation' => ['This reservation cannot be overridden in its current status.'],
                ]);
            }

            $targetSpace = Space::query()->whereKey($spaceId)->where('is_active', true)->lockForUpdate()->first();
            if (! $targetSpace) {
                throw ValidationException::withMessages(['space_id' => ['Select a valid active space.']]);
            }
            if ($targetSpace->isConfabAssignmentPool()) {
                throw ValidationException::withMessages([
                    'space_id' => ['Choose a specific room, not the general confab pool.'],
                ]);
            }

            $subject = $locked->user;
            if (! $subject) {
                throw ValidationException::withMessages(['reservation' => ['Reservation has no requester.']]);
            }

            $prevSpaceId = (int) $locked->space_id;
            $prevStart = $locked->start_at->copy();
            $prevEnd = $locked->end_at->copy();

            $conflicts = Reservation::query()
                ->where('space_id', $spaceId)
                ->blocking()
                ->overlapping($start, $end)
                ->where('id', '<>', $locked->id)
                ->lockForUpdate()
                ->with('user')
                ->get();

            $ownersById = [];
            foreach ($conflicts as $c) {
                if ($c->user) {
                    $ownersById[$c->id] = $c->user;
                }
            }

            [$displaceOk, $displaceMsg] = ReservationUserPriority::assertDisplacementsAllowed($subject, $ownersById);
            if (! $displaceOk) {
                throw ValidationException::withMessages(['slot' => [$displaceMsg ?? 'Cannot displace conflicting bookings.']]);
            }

            $displacedIds = [];
            foreach ($conflicts as $other) {
                $other->update([
                    'status' => Reservation::STATUS_RESCHEDULE_REQUIRED,
                ]);
                $displacedIds[] = $other->id;

                ReservationLog::create([
                    'reservation_id' => $other->id,
                    'actor_user_id' => $admin->id,
                    'actor_type' => ReservationLog::ACTOR_ADMIN,
                    'action' => ReservationLog::ACTION_DISPLACED,
                    'notes' => 'Slot taken by admin global override on reservation #'.$locked->id.'.',
                ]);

                if ($other->user) {
                    Mail::to($other->user->email)->send(new ReservationDisplacedByAdminOverrideMail(
                        $other->fresh(['space']),
                        $locked,
                        $reason,
                        $admin,
                        $targetSpace,
                        $start,
                        $end
                    ));

                    $other->user->notify(new DatabaseTextNotification(
                        'Reservation update required',
                        'Your booking was displaced by a library admin override. Please choose a new time or space.',
                        [
                            'kind' => 'reservation_displaced',
                            'reservation_id' => $other->id,
                            'admin_override_reservation_id' => $locked->id,
                        ]
                    ));
                }
            }

            $reservationNumber = $locked->reservation_number ?: ('RES-' . strtoupper(Str::random(8)));

            ReservationOverrideLog::create([
                'reservation_id' => $locked->id,
                'admin_user_id' => $admin->id,
                'previous_space_id' => $prevSpaceId,
                'previous_start_at' => $prevStart,
                'previous_end_at' => $prevEnd,
                'new_space_id' => $spaceId,
                'new_start_at' => $start,
                'new_end_at' => $end,
                'reason' => $reason,
                'displaced_reservation_ids' => $displacedIds === [] ? null : $displacedIds,
            ]);

            $locked->update([
                'space_id' => $spaceId,
                'start_at' => $start,
                'end_at' => $end,
                'status' => Reservation::STATUS_OVERRIDDEN,
                'reservation_number' => $reservationNumber,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'override_reason' => $reason,
                'overridden_by' => $admin->id,
                'overridden_at' => now(),
                'override_previous_space_id' => $prevSpaceId,
                'override_previous_start_at' => $prevStart,
                'override_previous_end_at' => $prevEnd,
            ]);

            ReservationLog::create([
                'reservation_id' => $locked->id,
                'actor_user_id' => $admin->id,
                'actor_type' => ReservationLog::ACTOR_ADMIN,
                'action' => ReservationLog::ACTION_OVERRIDE,
                'notes' => $reason,
            ]);

            $fresh = $locked->fresh(['user', 'space', 'approver', 'logs.actor']);
            if ($fresh && $fresh->user) {
                Mail::to($fresh->user->email)->send(new ReservationGloballyOverriddenMail($fresh, $admin, $prevStart, $prevEnd));

                $fresh->user->notify(new DatabaseTextNotification(
                    'Reservation changed by admin',
                    'Your reservation was updated by an administrator. Check your email for details.',
                    [
                        'kind' => 'reservation_admin_override',
                        'reservation_id' => $fresh->id,
                    ]
                ));
            }

            return $fresh ?? $locked;
        });
    }
}
