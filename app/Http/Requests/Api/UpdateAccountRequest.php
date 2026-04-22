<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use App\Support\AuthEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => AuthEmail::normalize((string) $this->input('email')),
            ]);
        }

        if ($this->has('password')) {
            $trimmed = trim((string) $this->input('password'));
            if ($trimmed === '') {
                $this->merge([
                    'password' => null,
                    'password_confirmation' => null,
                ]);
            } else {
                $this->merge([
                    'password' => $trimmed,
                    'password_confirmation' => trim((string) $this->input('password_confirmation')),
                ]);
            }
        }
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();
        $user->loadMissing('role');

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
        ];

        if ($user->isAdminPortalAccount()) {
            $rules['email'] = [
                'required',
                'string',
                'email:rfc',
                Rule::unique('users', 'email')->ignore($user->id),
            ];
            $rules['mobile_number'] = ['sometimes', 'nullable', 'string', 'min:7', 'max:32', 'regex:/^[0-9+ ()-]+$/'];
            $rules['password'] = ['nullable', 'string', 'min:8', 'max:255', 'confirmed'];
            $rules['current_password'] = [
                Rule::requiredIf(function () use ($user) {
                    $incoming = AuthEmail::normalize((string) $this->input('email'));
                    $emailChanged = $incoming !== '' && $incoming !== AuthEmail::normalize((string) $user->email);
                    $wantsPassword = filled($this->input('password'));

                    return $emailChanged || $wantsPassword;
                }),
                'nullable',
                'string',
            ];
        } else {
            $rules['mobile_number'] = ['required', 'string', 'min:7', 'max:32', 'regex:/^[0-9+ ()-]+$/'];
            $rules['email'] = ['prohibited'];
            $rules['current_password'] = ['prohibited'];
            $rules['password'] = ['prohibited'];
            $rules['password_confirmation'] = ['prohibited'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var User|null $user */
            $user = $this->user();
            if (!$user || $validator->errors()->isNotEmpty()) {
                return;
            }

            $user->loadMissing('role');
            if (!$user->isAdminPortalAccount()) {
                return;
            }

            $email = (string) $this->input('email');
            if ($email !== '' && !User::isAllowedDomain($email)) {
                $validator->errors()->add('email', 'Invalid email domain.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $emailChanged = AuthEmail::normalize($email) !== AuthEmail::normalize((string) $user->email);
            $wantsPassword = filled($this->input('password'));

            if (!$emailChanged && !$wantsPassword) {
                return;
            }

            $pwd = (string) $this->input('current_password');
            if ($pwd === '' || !Hash::check($pwd, (string) $user->password)) {
                $validator->errors()->add(
                    'current_password',
                    $emailChanged
                        ? 'Current password is required to change your email.'
                        : 'Enter your current password to set a new password.'
                );
            }
        });
    }
}
