<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use App\Support\AuthEmail;
use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
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
            'password' => ['required', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $email = (string) $this->input('email');
            if (!User::isAllowedDomain($email)) {
                $validator->errors()->add('email', 'Invalid email domain.');
            }
        });
    }
}

