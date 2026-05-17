<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Support\AuthEmail;
use App\Support\UserAffiliationChangePolicy;
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

            $type = $user->user_type ?? User::getUserTypeFromEmail($user->email);
            if ($type === User::USER_TYPE_STUDENT) {
                $rules['college_id'] = ['sometimes', 'integer', 'exists:colleges,id'];
                $rules['office_id'] = ['prohibited'];
            } elseif ($type === User::USER_TYPE_FACULTY_STAFF) {
                $rules['office_id'] = ['sometimes', 'integer', 'exists:offices,id'];
                $rules['college_id'] = ['prohibited'];
            } else {
                $rules['college_id'] = ['prohibited'];
                $rules['office_id'] = ['prohibited'];
            }
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
            if (! $user->isAdminPortalAccount()) {
                $this->validateAffiliationChange($validator, $user);

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

    private function validateAffiliationChange($validator, User $user): void
    {
        if ($validator->errors()->isNotEmpty() || ! UserAffiliationChangePolicy::appliesTo($user)) {
            return;
        }

        $type = $user->user_type ?? User::getUserTypeFromEmail($user->email);
        $changed = false;

        if ($type === User::USER_TYPE_STUDENT && $this->has('college_id')) {
            $changed = UserAffiliationChangePolicy::studentCollegeChanged(
                $user,
                $this->input('college_id') !== null ? (int) $this->input('college_id') : null
            );
        }

        if ($type === User::USER_TYPE_FACULTY_STAFF && $this->has('office_id')) {
            $changed = UserAffiliationChangePolicy::employeeOfficeChanged(
                $user,
                $this->input('office_id') !== null ? (int) $this->input('office_id') : null
            );
        }

        if (! $changed) {
            return;
        }

        if (! UserAffiliationChangePolicy::canChangeAffiliation($user)) {
            $field = $type === User::USER_TYPE_STUDENT ? 'college_id' : 'office_id';
            $validator->errors()->add($field, UserAffiliationChangePolicy::BLOCKED_MESSAGE);
        }
    }
}
