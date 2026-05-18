<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeanEmailMapping;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeanEmailMappingController extends Controller
{
    private function allowedAffiliationNamesForType(string $type): array
    {
        return match ($type) {
            DeanEmailMapping::TYPE_COLLEGE => User::allowedStudentColleges(),
            DeanEmailMapping::TYPE_OFFICE_DEPARTMENT => User::allowedFacultyOffices(),
            default => [],
        };
    }

    private function assertAffiliationNameAllowed(string $type, string $name): void
    {
        $allowed = $this->allowedAffiliationNamesForType($type);
        if ($allowed === [] || !in_array($name, $allowed, true)) {
            throw ValidationException::withMessages([
                'affiliation_name' => ['Select a valid affiliation for the chosen type (same list as user profile).'],
            ]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $items = DeanEmailMapping::query()
            ->orderBy('affiliation_type')
            ->orderBy('affiliation_name')
            ->get();

        Log::info('Dean email mappings index', [
            'count' => $items->count(),
            'by_type' => $items->groupBy('affiliation_type')->map->count(),
        ]);

        return ApiResponse::data($items->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'affiliation_type' => ['required', 'string', Rule::in([DeanEmailMapping::TYPE_COLLEGE, DeanEmailMapping::TYPE_OFFICE_DEPARTMENT])],
            'affiliation_name' => ['required', 'string', 'max:255'],
            'approver_name' => ['nullable', 'string', 'max:255'],
            'approver_email' => ['required', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['affiliation_name'] = trim($data['affiliation_name']);
        $this->assertAffiliationNameAllowed($data['affiliation_type'], $data['affiliation_name']);
        $data = DeanEmailMapping::prepareOfficeDepartmentAttributes($data);

        // Avoid ambiguous routing: prevent multiple active mappings for the same affiliation.
        if (($data['is_active'] ?? true) === true) {
            $existsQuery = DeanEmailMapping::query()
                ->where('affiliation_type', $data['affiliation_type'])
                ->where('is_active', true);

            if ($data['affiliation_type'] === DeanEmailMapping::TYPE_OFFICE_DEPARTMENT
                && ! empty($data['office_code'])) {
                $existsQuery->where('office_code', $data['office_code']);
            } else {
                $existsQuery->where('affiliation_name', $data['affiliation_name']);
            }

            $exists = $existsQuery->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'affiliation_name' => ['An active mapping for this affiliation already exists.'],
                ]);
            }
        }

        $row = DeanEmailMapping::create($data);

        return ApiResponse::message('Dean email mapping created.', $row, 201);
    }

    public function update(Request $request, DeanEmailMapping $deanEmailMapping): JsonResponse
    {
        $data = $request->validate([
            'affiliation_type' => ['sometimes', 'string', Rule::in([DeanEmailMapping::TYPE_COLLEGE, DeanEmailMapping::TYPE_OFFICE_DEPARTMENT])],
            'affiliation_name' => ['sometimes', 'string', 'max:255'],
            'approver_name' => ['nullable', 'string', 'max:255'],
            'approver_email' => ['sometimes', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('affiliation_name', $data)) {
            $data['affiliation_name'] = trim((string) $data['affiliation_name']);
        }

        $nextType = $data['affiliation_type'] ?? $deanEmailMapping->affiliation_type;
        $nextName = array_key_exists('affiliation_name', $data)
            ? (string) $data['affiliation_name']
            : $deanEmailMapping->affiliation_name;

        if (array_key_exists('affiliation_type', $data) || array_key_exists('affiliation_name', $data)) {
            $this->assertAffiliationNameAllowed($nextType, $nextName);
        }

        $merged = array_merge($deanEmailMapping->only([
            'affiliation_type',
            'affiliation_name',
            'office_code',
            'office_id',
            'is_active',
        ]), $data);
        $data = array_merge($data, DeanEmailMapping::prepareOfficeDepartmentAttributes($merged));
        unset($merged);

        $nextActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) $deanEmailMapping->is_active;

        if ($nextActive) {
            $existsQuery = DeanEmailMapping::query()
                ->where('id', '<>', $deanEmailMapping->id)
                ->where('affiliation_type', $nextType)
                ->where('is_active', true);

            $officeCode = $data['office_code'] ?? DeanEmailMapping::normalizeOfficeCode($nextName);
            if ($nextType === DeanEmailMapping::TYPE_OFFICE_DEPARTMENT && $officeCode !== null) {
                $existsQuery->where('office_code', $officeCode);
            } else {
                $existsQuery->where('affiliation_name', $nextName);
            }

            $exists = $existsQuery->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'affiliation_name' => ['An active mapping for this affiliation already exists.'],
                ]);
            }
        }

        $deanEmailMapping->update($data);

        return ApiResponse::message('Dean email mapping updated.', $deanEmailMapping->fresh());
    }

    public function destroy(DeanEmailMapping $deanEmailMapping): JsonResponse
    {
        $deanEmailMapping->delete();

        return ApiResponse::message('Dean email mapping deleted.');
    }
}

