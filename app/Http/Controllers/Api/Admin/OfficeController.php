<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeanEmailMapping;
use App\Models\Office;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfficeController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::data(
            Office::query()->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('offices', 'name')],
            'approver_email' => ['nullable', 'email', 'max:255'],
            'approver_name' => ['nullable', 'string', 'max:255'],
            'mapping_active' => ['sometimes', 'boolean'],
        ]);

        $name = trim((string) $data['name']);
        $office = Office::query()->create(['name' => $name]);

        if (!empty($data['approver_email'])) {
            DeanEmailMapping::query()->create([
                'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
                'affiliation_name' => $office->name,
                'office_id' => $office->id,
                'approver_name' => $data['approver_name'] ? trim((string) $data['approver_name']) : null,
                'approver_email' => trim((string) $data['approver_email']),
                'is_active' => array_key_exists('mapping_active', $data) ? (bool) $data['mapping_active'] : true,
            ]);
        }

        return ApiResponse::message('Office created.', $office->fresh(), 201);
    }

    public function update(Request $request, Office $office): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('offices', 'name')->ignore($office->id)],
            'approver_email' => ['nullable', 'email', 'max:255'],
            'approver_name' => ['nullable', 'string', 'max:255'],
            'mapping_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $office->update(['name' => trim((string) $data['name'])]);
            DeanEmailMapping::query()
                ->where('office_id', $office->id)
                ->update(['affiliation_name' => $office->name, 'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT]);
            User::query()
                ->where('office_id', $office->id)
                ->update(['college_office' => $office->name]);
        }

        if (array_key_exists('approver_email', $data) || array_key_exists('approver_name', $data) || array_key_exists('mapping_active', $data)) {
            $mapping = DeanEmailMapping::query()->where('office_id', $office->id)->first();
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
                    'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
                    'affiliation_name' => $office->name,
                    'office_id' => $office->id,
                    'approver_name' => $data['approver_name'] ? trim((string) $data['approver_name']) : null,
                    'approver_email' => $email,
                    'is_active' => array_key_exists('mapping_active', $data) ? (bool) $data['mapping_active'] : true,
                ]);
            }
        }

        return ApiResponse::message('Office updated.', $office->fresh());
    }

    public function destroy(Office $office): JsonResponse
    {
        $hasUsers = User::query()->where('office_id', $office->id)->exists();
        if ($hasUsers) {
            throw ValidationException::withMessages([
                'office' => ['Cannot delete: this office is associated with existing users.'],
            ]);
        }

        $hasActiveMapping = DeanEmailMapping::query()
            ->where('office_id', $office->id)
            ->where('is_active', true)
            ->exists();
        if ($hasActiveMapping) {
            throw ValidationException::withMessages([
                'office' => ['Cannot delete: this office has an active dean/approver email mapping.'],
            ]);
        }

        DeanEmailMapping::query()->where('office_id', $office->id)->delete();
        $office->delete();

        return ApiResponse::message('Office deleted.');
    }
}

