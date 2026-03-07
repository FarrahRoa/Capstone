<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Space;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'space_id' => 'required_without:date|nullable|exists:spaces,id',
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($request->input('date'))->startOfDay();
        $spaceId = $request->input('space_id');

        $spaces = $spaceId
            ? Space::where('id', $spaceId)->where('is_active', true)->get()
            : Space::where('is_active', true)->orderBy('name')->get();

        if ($spaces->isEmpty()) {
            return response()->json(['message' => 'No spaces found.'], 404);
        }

        $result = [];
        foreach ($spaces as $space) {
            $reserved = Reservation::where('space_id', $space->id)
                ->whereIn('status', [Reservation::STATUS_APPROVED, Reservation::STATUS_PENDING_APPROVAL, Reservation::STATUS_EMAIL_VERIFICATION_PENDING])
                ->whereDate('start_at', $date)
                ->orderBy('start_at')
                ->get(['id', 'start_at', 'end_at', 'status']);

            $result[] = [
                'space' => $space,
                'reserved_slots' => $reserved->map(fn ($r) => [
                    'id' => $r->id,
                    'start_at' => $r->start_at->toIso8601String(),
                    'end_at' => $r->end_at->toIso8601String(),
                    'status' => $r->status,
                ]),
            ];
        }

        return response()->json($result);
    }
}
