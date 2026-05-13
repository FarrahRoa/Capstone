<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GlobalReservationOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canDo('reservation.override');
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:5000',
            'space_id' => 'required|integer|exists:spaces,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
        ];
    }
}
