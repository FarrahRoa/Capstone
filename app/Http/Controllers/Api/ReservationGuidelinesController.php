<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use Illuminate\Http\JsonResponse;

class ReservationGuidelinesController extends Controller
{
    public function show(): JsonResponse
    {
        $doc = PolicyDocument::reservationGuidelines();

        return response()->json([
            'slug' => $doc->slug,
            'content' => $doc->content,
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ]);
    }
}
