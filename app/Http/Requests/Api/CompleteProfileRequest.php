<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            // Reuse existing column name; acts as "college OR office" based on inferred user_type.
            'college_office' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'min:7', 'max:32'],
        ];
    }
}

