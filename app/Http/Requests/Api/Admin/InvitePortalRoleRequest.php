<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Role;
use App\Models\User;
use App\Support\AuthEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvitePortalRoleRequest extends FormRequest
{
    public const ROLE_LIBRARIAN = 'librarian';

    public const ROLE_STUDENT_ASSISTANT = 'student_assistant';

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
        if ($this->has('role_slug')) {
            $this->merge([
                'role_slug' => strtolower(trim((string) $this->input('role_slug'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc'],
            'role_slug' => ['required', 'string', Rule::in([self::ROLE_LIBRARIAN, self::ROLE_STUDENT_ASSISTANT])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $email = (string) $this->input('email');
            $roleSlug = (string) $this->input('role_slug');

            if (!Role::where('slug', $roleSlug)->exists()) {
                $validator->errors()->add('role_slug', 'Selected role is not configured.');
            }

            if ($roleSlug === self::ROLE_LIBRARIAN && !User::isAllowedAdminInviteDomain($email)) {
                $validator->errors()->add('email', 'Librarian invites must use an @xu.edu.ph email.');
            }

            if ($roleSlug === self::ROLE_STUDENT_ASSISTANT) {
                $domain = strtolower(substr($email, strrpos($email, '@') + 1));
                if ($domain !== User::DOMAIN_MY_XU) {
                    $validator->errors()->add('email', 'Student Assistant invites must use an @my.xu.edu.ph email.');
                }
            }
        });
    }
}
