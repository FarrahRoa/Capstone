<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Support\ApiResponse;
use App\Support\SpaceGuidelineDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $operational = $request->boolean('operational')
            && $request->user()
            && $request->user()->canDo('reservation.view_all');

        $spaces = Space::where('is_active', true)->orderBy('name')->get();

        $payload = $spaces->map(function (Space $space) use ($operational) {
            $displayName = $operational
                ? $space->scheduleOperationalDisplayName()
                : $space->userFacingName();

            $base = $space->toArray();

            return array_merge($base, [
                'name' => $displayName,
                /** Stored room name for showcase (e.g. Confab 1); {@see $displayName} stays booking-facing. */
                'record_name' => $base['name'] ?? $space->name,
                'guideline_details' => SpaceGuidelineDetails::forApi($space->guideline_details),
            ]);
        })->values()->all();

        return ApiResponse::data($payload);
    }
}
