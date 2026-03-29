<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash|unique:spaces,slug',
            'type' => 'required|string|in:avr,lobby,boardroom,medical_confab,confab',
            'capacity' => 'nullable|integer|min:1|max:65535',
            'is_active' => 'sometimes|boolean',
        ];
    }
}

