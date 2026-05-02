<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpaceRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash|unique:spaces,slug',
            'type' => 'required|string|in:avr,lobby,boardroom,medical_confab,confab,lecture',
            'capacity' => 'nullable|integer|min:1|max:65535',
            'is_active' => 'sometimes|boolean',
            'is_confab_pool' => 'sometimes|boolean',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
        ];
    }
}

