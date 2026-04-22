<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use App\Support\ApiResponse;
use App\Support\ConfabGuidelinesComparison;
use Illuminate\Http\JsonResponse;

class ReservationGuidelinesController extends Controller
{
    public function show(): JsonResponse
    {
        $doc = PolicyDocument::reservationGuidelines();
        $confabDoc = PolicyDocument::confabReservationGuidelines();

        return ApiResponse::data([
            'slug' => $doc->slug,
            'content' => $doc->content,
            'updated_at' => $doc->updated_at?->toIso8601String(),
            'confab_guidelines_content' => $confabDoc->content,
            'confab_guidelines_updated_at' => $confabDoc->updated_at?->toIso8601String(),
            'confab_room_comparisons' => ConfabGuidelinesComparison::physicalConfabRoomsPayload(),
        ]);
    }
}
