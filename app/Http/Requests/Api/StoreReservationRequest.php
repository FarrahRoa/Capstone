<?php

namespace App\Http\Requests\Api;

use App\Models\Reservation;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
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
            if ($validator->errors()->has('start_at') || $validator->errors()->has('end_at')) {
                return;
            }
            $overlap = Reservation::where('space_id', $this->input('space_id'))
                ->whereIn('status', [Reservation::STATUS_APPROVED, Reservation::STATUS_PENDING_APPROVAL, Reservation::STATUS_EMAIL_VERIFICATION_PENDING])
                ->where(function ($q) {
                    $q->whereBetween('start_at', [$this->input('start_at'), $this->input('end_at')])
                        ->orWhereBetween('end_at', [$this->input('start_at'), $this->input('end_at')])
                        ->orWhere(function ($q2) {
                            $q2->where('start_at', '<=', $this->input('start_at'))
                                ->where('end_at', '>=', $this->input('end_at'));
                        });
                })
                ->exists();
            if ($overlap) {
                $validator->errors()->add('slot', 'Selected time slot is not available.');
            }
        });
    }
}
