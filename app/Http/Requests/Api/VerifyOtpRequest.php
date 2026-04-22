<?php

namespace App\Http\Requests\Api;

use App\Support\AuthEmail;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => AuthEmail::normalize($this->input('email')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc'],
            'otp' => ['required', 'digits:6'],
        ];
    }
}
