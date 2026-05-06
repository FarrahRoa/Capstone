<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Holiday::query()
            ->orderBy('date')
            ->orderBy('name')
            ->get();

        return ApiResponse::data($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'is_recurring' => ['sometimes', 'boolean'],
        ]);

        $row = Holiday::query()->create([
            'name' => trim((string) $data['name']),
            'date' => (string) $data['date'],
            'is_recurring' => (bool) ($data['is_recurring'] ?? false),
        ]);

        return ApiResponse::message('Holiday created.', $row, 201);
    }

    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'is_recurring' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) $data['name']);
        }

        $holiday->update($data);

        return ApiResponse::message('Holiday updated.', $holiday->fresh());
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return ApiResponse::message('Holiday deleted.');
    }
}

