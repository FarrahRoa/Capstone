<?php

namespace App\Http\Requests\Reservation;

use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Models\Holiday;
use App\Models\PolicyDocument;
use App\Support\ReservationDeanRouting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

class UpdateReservationRequest extends FormRequest
{
    /** @var int[] */
    private const ALLOWED_MINUTES = [0, 30];

    public function authorize(): bool
    {
        return $this->user() && $this->user()->canDo('reservation.create');
    }

    public function rules(): array
    {
        return [
            'space_id' => 'required|exists:spaces,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'event_request_type' => [
                'nullable',
                'string',
                Rule::in([Reservation::EVENT_REQUEST_ORGANIZATION, Reservation::EVENT_REQUEST_EMPLOYEE]),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->user()) {
                return;
            }
            if ($validator->errors()->has('space_id')) {
                return;
            }

            $space = Space::find($this->input('space_id'));
            if (!$space) {
                return;
            }
            $blocked = $this->user()->roomReservationBlockedMessage($space);
            if ($blocked !== null) {
                $validator->errors()->add('space_id', $blocked);
                return;
            }

            if ($space->type === Space::TYPE_CONFAB && ! $space->isConfabAssignmentPool() && ! $this->user()->isAdmin()) {
                $validator->errors()->add(
                    'space_id',
                    'Reserve the general Confab slot; a specific confab room is assigned when staff approves your request.'
                );

                return;
            }
            if ($validator->errors()->has('start_at') || $validator->errors()->has('end_at')) {
                return;
            }

            try {
                $tz = (string) config('app.timezone');
                $start = Carbon::parse((string) $this->input('start_at'), $tz);
                $end = Carbon::parse((string) $this->input('end_at'), $tz);
            } catch (Throwable) {
                return;
            }

            $todayStart = Carbon::now($tz)->startOfDay();
            if ($start->copy()->startOfDay()->lt($todayStart)) {
                $validator->errors()->add('start_at', 'Past dates are not reservable.');
                return;
            }

            $holiday = Holiday::matchForDate($start, $tz);
            if ($holiday !== null) {
                $validator->errors()->add('start_at', 'Cannot reserve on a holiday.');
                return;
            }

            if ($end->lte(Carbon::now($tz))) {
                $validator->errors()->add('end_at', 'Reservation must be in the future.');
                return;
            }

            if ((int) $start->second !== 0 || (int) $end->second !== 0) {
                $validator->errors()->add('slot', 'Seconds must be :00.');
                return;
            }

            if (! in_array((int) $start->minute, self::ALLOWED_MINUTES, true)) {
                $validator->errors()->add('start_at', 'Start time minutes must be :00 or :30.');
                return;
            }

            if (! in_array((int) $end->minute, self::ALLOWED_MINUTES, true)) {
                $validator->errors()->add('end_at', 'End time minutes must be :00 or :30.');
                return;
            }

            if (PolicyDocument::reservationOutsideOperatingHours($start, $end, $tz)) {
                $validator->errors()->add('start_at', 'The selected time is outside the library\'s operating hours.');
                return;
            }

            $userType = $this->user()->user_type ?? User::getUserTypeFromEmail((string) $this->user()->email);
            $maxMinutes = $userType === User::USER_TYPE_STUDENT ? 120 : 180;
            $maxHours = $maxMinutes / 60;
            $durationMinutes = $start->diffInMinutes($end);
            if ($durationMinutes > $maxMinutes) {
                $validator->errors()->add(
                    'end_at',
                    "You have exceeded your maximum booking limit of {$maxHours} hours for your account type."
                );
                return;
            }

            $reservation = $this->route('reservation');
            if ($reservation instanceof Reservation) {
                $reservation->loadMissing('space');
                if ($reservation->space?->isConfabAssignmentPool()
                    && (int) $this->input('space_id') !== (int) $reservation->space_id) {
                    $validator->errors()->add(
                        'space_id',
                        'General confab reservations keep the same slot until a librarian assigns a specific room at approval.'
                    );
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $reservation = $this->route('reservation');
            if (! $reservation instanceof Reservation) {
                return;
            }
            if (! in_array($reservation->status, [
                Reservation::STATUS_EMAIL_VERIFICATION_PENDING,
                Reservation::STATUS_PENDING_DEAN_APPROVAL,
                Reservation::STATUS_PENDING_APPROVAL,
                Reservation::STATUS_APPROVED,
            ], true)) {
                return;
            }
            $tz = (string) config('app.timezone');
            $now = Carbon::now($tz);
            if ($reservation->end_at && $reservation->end_at->copy()->timezone($tz)->lte($now)) {
                return;
            }

            $target = Space::find((int) $this->input('space_id'));
            if (! $target) {
                return;
            }

            $effectiveAudience = trim((string) $this->input('event_request_type', ''));
            if ($effectiveAudience === '') {
                $effectiveAudience = (string) ($reservation->event_request_type ?? '');
            }

            ReservationDeanRouting::assertAudienceAndDeanMappingForReservation(
                $target,
                $this->user(),
                ReservationDeanRouting::spaceUsesAvrLobbyAudienceRouting($target) ? $effectiveAudience : null
            );
        });
    }
}
