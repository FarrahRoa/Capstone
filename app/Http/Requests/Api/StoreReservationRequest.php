<?php

namespace App\Http\Requests\Api;

use App\Models\Reservation;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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
                throw ValidationException::withMessages([
                    'reservation' => ['You already have 3 active reservations. Cancel or complete an existing reservation before making another.'],
                ]);
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
        });
    }
}
