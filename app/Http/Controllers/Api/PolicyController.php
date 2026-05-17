<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\PolicyDocument;
use App\Support\ApiResponse;
use App\Support\ReservationLeadTimePolicy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PolicyController extends Controller
{
    /**
     * Authoritative server instant for booking cutoff rules (Manila civil context).
     */
    public function bookingClock(): JsonResponse
    {
        $now = Carbon::now(ReservationLeadTimePolicy::TZ);

        return ApiResponse::data([
            'now_iso' => $now->toIso8601String(),
            'timezone' => ReservationLeadTimePolicy::TZ,
        ]);
    }

    public function operatingHours(): JsonResponse
    {
        try {
            $doc = PolicyDocument::operatingHours();
            $hours = PolicyDocument::decodedOperatingHours();

            $holidays = Holiday::query()
                ->orderBy('date')
                ->orderBy('name')
                ->get(['id', 'name', 'date', 'is_recurring']);

            return ApiResponse::data([
                'slug' => $doc->slug,
                'hours' => [
                    'day_start' => $hours['day_start'],
                    'day_end' => $hours['day_end'],
                    'weekend_day_start' => $hours['weekend_day_start'],
                    'weekend_day_end' => $hours['weekend_day_end'],
                    'max_booking_date' => $hours['max_booking_date'],
                ],
                'holidays' => $holidays,
                'updated_at' => $doc->updated_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('operatingHours failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json(['error' => 'Failed to load operating hours'], 500);
        }
    }
}
