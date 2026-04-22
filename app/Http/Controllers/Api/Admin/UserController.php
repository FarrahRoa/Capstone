<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\InvitePortalRoleRequest;
use App\Mail\LibrarianInviteMail;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'sort' => 'sometimes|string|in:name,recent',
        ]);

        $search = trim((string) $request->query('search', ''));
        $roleSlug = trim((string) $request->query('role', ''));
        $sort = (string) $request->query('sort', 'name');
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $page = max((int) $request->query('page', 1), 1);

        $query = User::query()->with('role');
        if ($sort === 'recent') {
            $query->orderByDesc('created_at');
        } else {
            $query->orderBy('name');
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }
        if ($roleSlug !== '') {
            $query->whereHas('role', function ($q) use ($roleSlug) {
                $q->where('slug', $roleSlug);
            });
        }

        $columns = [
            'id',
            'name',
            'email',
            'role_id',
            'user_type',
            'college_office',
            'profile_completed_at',
            'med_confab_eligible',
            'boardroom_eligible',
            'created_at',
            'admin_invited_at',
            'admin_invite_expires_at',
            'admin_password_set_at',
        ];

        return response()->json(
            $query->paginate($perPage, $columns, 'page', $page)
        );
    }

    public function roles(): JsonResponse
    {
        return ApiResponse::data(
            Role::query()->orderBy('name')->get(['id', 'name', 'slug'])
        );
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'med_confab_eligible' => 'sometimes|boolean',
            'boardroom_eligible' => 'sometimes|boolean',
            'role_id' => 'sometimes|integer|exists:roles,id',
            'role_slug' => 'sometimes|string|exists:roles,slug',
        ]);

        if (empty($data)) {
            return response()->json([
                'message' => 'No updatable fields provided.',
            ], 422);
        }

        $roleId = $data['role_id'] ?? null;
        if (array_key_exists('role_slug', $data)) {
            $role = Role::where('slug', $data['role_slug'])->first();
            if (!$role) {
                return response()->json([
                    'message' => 'Selected role does not exist.',
                ], 422);
            }
            $roleId = $role->id;
        }

        if ($roleId !== null) {
            $targetRole = Role::find($roleId);
            $currentRole = $user->role;
            if ($currentRole && $currentRole->slug === 'admin' && $targetRole && $targetRole->slug !== 'admin') {
                $adminRole = Role::where('slug', 'admin')->first();
                if ($adminRole) {
                    $adminCount = User::where('role_id', $adminRole->id)->count();
                    if ($adminCount <= 1) {
                        return response()->json([
                            'message' => 'Cannot remove the last remaining admin.',
                        ], 422);
                    }
                }
            }
            $data['role_id'] = $roleId;
        }
        unset($data['role_slug']);

        $user->update($data);

        return ApiResponse::data($user->fresh()->load('role'));
    }

    public function invitePortalRole(InvitePortalRoleRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');
        $roleSlug = (string) $request->input('role_slug');

        $role = Role::where('slug', $roleSlug)->first();
        if (!$role) {
            return response()->json([
                'message' => "Role '{$roleSlug}' is not configured.",
            ], 422);
        }

        $plainPassword = Str::password(16, symbols: true, numbers: true, letters: true);

        $user = User::findByNormalizedEmail($email);
        if (!$user) {
            $user = User::create([
                'name' => Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'role_id' => $role->id,
                'user_type' => User::getUserTypeFromEmail($email),
                'is_activated' => true,
                'otp' => null,
                'otp_hash' => null,
                'otp_expires_at' => null,
            ]);
        } else {
            $user->update([
                'role_id' => $role->id,
                'password' => Hash::make($plainPassword),
                'user_type' => $user->user_type ?? User::getUserTypeFromEmail($email),
                'is_activated' => true,
                'otp' => null,
                'otp_hash' => null,
                'otp_expires_at' => null,
            ]);
        }

        $user->update([
            'admin_invite_token_hash' => null,
            'admin_invite_expires_at' => null,
            'admin_invited_at' => now(),
            'admin_password_set_at' => now(),
        ]);

        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $adminSignInUrl = $frontend . '/admin/login';

        Mail::to($user->email)->send(new LibrarianInviteMail(
            email: $user->email,
            roleName: $role->name,
            temporaryPassword: $plainPassword,
            adminSignInUrl: $adminSignInUrl,
        ));

        return ApiResponse::data([
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->fresh()->load('role')->role?->slug,
            'invited_at' => optional($user->admin_invited_at)?->toISOString(),
        ]);
    }
}
