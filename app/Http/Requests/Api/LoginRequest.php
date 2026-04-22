<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use App\Support\AuthEmail;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public const ACTION_SIGN_IN = 'sign_in';
    public const ACTION_SIGN_UP = 'sign_up';

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
            'account_type' => ['required', 'in:' . implode(',', [User::PUBLIC_ACCOUNT_STUDENT, User::PUBLIC_ACCOUNT_EMPLOYEE])],
            'action' => ['required', 'in:' . implode(',', [self::ACTION_SIGN_IN, self::ACTION_SIGN_UP])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $email = (string) $this->input('email');

            $accountType = (string) $this->input('account_type');

            if (!User::isAllowedDomain($email)) {
                $validator->errors()->add('email', 'Invalid email domain.');
                return;
            }

            if (!User::emailMatchesPublicAccountType($accountType, $email)) {
                $validator->errors()->add(
                    'email',
                    $accountType === User::PUBLIC_ACCOUNT_STUDENT
                        ? 'Student accounts must use @my.xu.edu.ph.'
                        : 'Employee/Staff accounts must use @xu.edu.ph.'
                );
            }
        });
    }
}
