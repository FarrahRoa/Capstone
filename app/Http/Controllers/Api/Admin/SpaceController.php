<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSpaceRequest;
use App\Http\Requests\Admin\UpdateSpaceRequest;
use App\Models\Space;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $data = $request->safe()->except(['image']);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('spaces', 'public');
        }

        $space = Space::create($data);

        return ApiResponse::data($space, 201);
    }

    public function update(UpdateSpaceRequest $request, Space $space): JsonResponse
    {
        $data = $request->safe()->except(['image', 'clear_image']);

        if ($request->hasFile('image')) {
            $this->deleteStoredSpaceImage($space);
            $data['image_path'] = $request->file('image')->store('spaces', 'public');
        } elseif ($request->boolean('clear_image')) {
            $this->deleteStoredSpaceImage($space);
            $data['image_path'] = null;
        }

        if ($data !== []) {
            $space->update($data);
        }

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

    private function deleteStoredSpaceImage(Space $space): void
    {
        $path = $space->image_path;
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}

