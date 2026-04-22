<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreSpaceRequest;
use App\Http\Requests\Api\Admin\UpdateSpaceRequest;
use App\Models\Space;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:avr,lobby,boardroom,medical_confab,confab,lecture',
        ]);

        $query = Space::query()->orderBy('name');
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%');
            });
        }
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        return ApiResponse::data($query->get());
    }

    public function store(StoreSpaceRequest $request): JsonResponse
    {
        $space = Space::create($request->validated());

        return ApiResponse::data($space, 201);
    }

    public function update(UpdateSpaceRequest $request, Space $space): JsonResponse
    {
        $space->update($request->validated());

        return ApiResponse::data($space->fresh());
    }

    public function toggleActive(Request $request, Space $space): JsonResponse
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $space->update([
            'is_active' => $data['is_active'],
        ]);

        return ApiResponse::data($space->fresh());
    }
}

