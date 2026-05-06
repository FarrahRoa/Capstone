<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\DeanEmailMapping;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CollegeController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::data(
            College::query()->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('colleges', 'name')],
            'approver_email' => ['nullable', 'email', 'max:255'],
            'approver_name' => ['nullable', 'string', 'max:255'],
            'mapping_active' => ['sometimes', 'boolean'],
        ]);

        $name = trim((string) $data['name']);
        $college = College::query()->create(['name' => $name]);

        if (!empty($data['approver_email'])) {
            DeanEmailMapping::query()->create([
                'affiliation_type' => DeanEmailMapping::TYPE_COLLEGE,
                'affiliation_name' => $college->name,
                'college_id' => $college->id,
                'approver_name' => $data['approver_name'] ? trim((string) $data['approver_name']) : null,
                'approver_email' => trim((string) $data['approver_email']),
                'is_active' => array_key_exists('mapping_active', $data) ? (bool) $data['mapping_active'] : true,
            ]);
        }

        return ApiResponse::message('College created.', $college->fresh(), 201);
    }

    public function update(Request $request, College $college): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('colleges', 'name')->ignore($college->id)],
            'approver_email' => ['nullable', 'email', 'max:255'],
            'approver_name' => ['nullable', 'string', 'max:255'],
            'mapping_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $college->update(['name' => trim((string) $data['name'])]);
            // Keep legacy name field in mapping consistent for display.
            DeanEmailMapping::query()
                ->where('college_id', $college->id)
                ->update(['affiliation_name' => $college->name, 'affiliation_type' => DeanEmailMapping::TYPE_COLLEGE]);
            // Keep legacy string on users in sync (until fully removed).
            User::query()
                ->where('college_id', $college->id)
                ->update(['college_office' => $college->name]);
        }

        if (array_key_exists('approver_email', $data) || array_key_exists('approver_name', $data) || array_key_exists('mapping_active', $data)) {
            $mapping = DeanEmailMapping::query()->where('college_id', $college->id)->first();
            $email = array_key_exists('approver_email', $data) ? trim((string) ($data['approver_email'] ?? '')) : null;
            if ($email === '') {
                $email = null;
            }
            if ($mapping) {
                $patch = [];
                if (array_key_exists('approver_name', $data)) $patch['approver_name'] = $data['approver_name'] ? trim((string) $data['approver_name']) : null;
                if (array_key_exists('approver_email', $data) && $email !== null) $patch['approver_email'] = $email;
                if (array_key_exists('mapping_active', $data)) $patch['is_active'] = (bool) $data['mapping_active'];
                if ($patch !== []) $mapping->update($patch);
            } elseif ($email !== null) {
                DeanEmailMapping::query()->create([
                    'affiliation_type' => DeanEmailMapping::TYPE_COLLEGE,
                    'affiliation_name' => $college->name,
                    'college_id' => $college->id,
                    'approver_name' => $data['approver_name'] ? trim((string) $data['approver_name']) : null,
                    'approver_email' => $email,
                    'is_active' => array_key_exists('mapping_active', $data) ? (bool) $data['mapping_active'] : true,
                ]);
            }
        }

        return ApiResponse::message('College updated.', $college->fresh());
    }

    public function destroy(College $college): JsonResponse
    {
        $hasUsers = User::query()->where('college_id', $college->id)->exists();
        if ($hasUsers) {
            throw ValidationException::withMessages([
                'college' => ['Cannot delete: this college is associated with existing users.'],
            ]);
        }

        $hasActiveMapping = DeanEmailMapping::query()
            ->where('college_id', $college->id)
            ->where('is_active', true)
            ->exists();
        if ($hasActiveMapping) {
            throw ValidationException::withMessages([
                'college' => ['Cannot delete: this college has an active dean/approver email mapping.'],
            ]);
        }

        // Delete inactive mapping rows too (optional cleanup).
        DeanEmailMapping::query()->where('college_id', $college->id)->delete();
        $college->delete();

        return ApiResponse::message('College deleted.');
    }
}

