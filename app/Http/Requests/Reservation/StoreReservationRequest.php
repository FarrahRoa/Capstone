<?php

namespace App\Http\Requests\Reservation;

use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Models\Holiday;
use App\Models\PolicyDocument;
use App\Support\BookingSlotCutoff;
use App\Support\ReservationDeanRouting;
use App\Support\ReservationLeadTimePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

class StoreReservationRequest extends FormRequest
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
            'start_at' => 'required|date|after:now',
            'end_at' => 'required|date|after:start_at',
            'purpose' => 'nullable|string|max:1000',
            'event_title' => 'nullable|string|max:255',
            'event_description' => 'nullable|string|max:5000',
            'participant_count' => 'nullable|integer|min:1|max:10000',
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

            $tz = (string) config('app.timezone');
            $activeCount = Reservation::query()
                ->where('user_id', $this->user()->id)
                ->whereIn('status', Reservation::activeUserLimitStatuses())
                ->where('end_at', '>', Carbon::now($tz))
                ->count();

            if ($activeCount >= 3) {
                $validator->errors()->add(
                    'reservation',
                    'You already have 3 active reservations. Cancel or complete an existing reservation before making another.'
                );

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

            if (PolicyDocument::reservationBeyondMaxBookingDate($start, $end, $tz)) {
                $validator->errors()->add('start_at', PolicyDocument::maxBookingDateValidationMessage());

                return;
            }

            if (BookingSlotCutoff::reservationStartAtOrAfterCutoff($start, $tz)) {
                $validator->errors()->add('start_at', BookingSlotCutoff::validationMessage());

                return;
            }

            $leadTimeMessage = ReservationLeadTimePolicy::messageIfBlockedFor($this->user(), $start, $end);
            if ($leadTimeMessage !== null) {
                $validator->errors()->add('start_at', $leadTimeMessage);

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

            $needsEventDetails = in_array((string) $space->slug, ['avr', 'lobby'], true)
                || in_array((string) $space->type, [Space::TYPE_CONFAB, Space::TYPE_MEDICAL_CONFAB, 'lecture'], true);

            if ($needsEventDetails) {
                if (trim((string) $this->input('event_title', '')) === '') {
                    $validator->errors()->add('event_title', 'Reservation title is required for this space.');

                    return;
                }
                if ((int) $this->input('participant_count', 0) <= 0) {
                    $validator->errors()->add('participant_count', 'Participant count is required for this space.');

                    return;
                }
            }

            // Reservation Guidelines are the authoritative source for seating capacity.
            // In this codebase the admin "Reservation Guidelines" screen persists the capacity per space in `spaces.capacity`.
            $capRaw = $space->capacity;
            if ($capRaw !== null && (int) $capRaw > 0) {
                // Back-compat: older clients may send `expected_attendees`; current UI uses `participant_count`.
                $expectedAttendeesRaw = $this->input('expected_attendees', null);
                $participantCountRaw = $this->input('participant_count', null);

                $field = $expectedAttendeesRaw !== null ? 'expected_attendees' : 'participant_count';
                $valRaw = $expectedAttendeesRaw !== null ? $expectedAttendeesRaw : $participantCountRaw;
                $val = (int) ($valRaw ?? 0);

                if ($val > (int) $capRaw) {
                    $validator->errors()->add($field, 'Exceeded the Seating capacity of ' . $space->userFacingName());

                    return;
                }
            }

            if (! $space->isConfabAssignmentPool()) {
                $conflict = Reservation::conflictsExist(
                    (int) $this->input('space_id'),
                    $this->input('start_at'),
                    $this->input('end_at')
                );
                if ($conflict) {
                    $validator->errors()->add('slot', 'Selected time slot is not available.');
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                ReservationDeanRouting::assertAudienceAndDeanMappingForReservation(
                    $space,
                    $this->user(),
                    $this->input('event_request_type')
                );
            } catch (Throwable) {
                $validator->errors()->add('event_request_type', 'Invalid reservation audience for this space.');
            }
        });
    }
}
