<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\PolicyDocument;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PolicyController extends Controller
{
    public function operatingHours(): JsonResponse
    {
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
            ],
            'holidays' => $holidays,
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ]);
    }
}

