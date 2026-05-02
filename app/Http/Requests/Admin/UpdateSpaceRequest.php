<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('capacity') && $this->input('capacity') === '') {
            $this->merge(['capacity' => null]);
        }
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
            'type' => 'sometimes|required|string|in:avr,lobby,boardroom,medical_confab,confab,lecture',
            'capacity' => 'sometimes|nullable|integer|min:1|max:65535',
            'is_active' => 'sometimes|boolean',
            'is_confab_pool' => 'sometimes|boolean',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
            'clear_image' => 'sometimes|boolean',
        ];
    }
}

