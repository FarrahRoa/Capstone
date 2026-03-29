<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $search = trim((string) $request->query('search', ''));
        $roleSlug = trim((string) $request->query('role', ''));
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $page = max((int) $request->query('page', 1), 1);

        $query = User::query()->with('role')->orderBy('name');
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
            'med_confab_eligible',
            'boardroom_eligible',
        ];

        return response()->json(
            $query->paginate($perPage, $columns, 'page', $page)
        );
    }

    public function roles(): JsonResponse
    {
        return response()->json(
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

        return response()->json(
            $user->fresh()->load('role')
        );
    }
}
