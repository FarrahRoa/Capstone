<?php

namespace App\Http\Requests\Api;

use App\Models\Reservation;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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
        });
    }
}
