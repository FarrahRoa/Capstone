<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('spaces', 'slug')->ignore($this->space->id),
            ],
            'type' => 'sometimes|required|string|in:avr,lobby,boardroom,medical_confab,confab',
            'capacity' => 'sometimes|nullable|integer|min:1|max:65535',
            'is_active' => 'sometimes|boolean',
        ];
    }
}

