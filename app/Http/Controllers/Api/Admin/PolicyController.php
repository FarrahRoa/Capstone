<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicyController extends Controller
{
    public function showReservationGuidelines(): JsonResponse
    {
        $doc = PolicyDocument::reservationGuidelines();

        return response()->json([
            'slug' => $doc->slug,
            'content' => $doc->content,
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ]);
    }

    public function updateReservationGuidelines(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => 'required|string|max:100000',
        ]);

        $doc = PolicyDocument::reservationGuidelines();
        $doc->update(['content' => $data['content']]);
        $doc->refresh();

        return response()->json([
            'message' => 'Reservation guidelines saved.',
            'slug' => $doc->slug,
            'content' => $doc->content,
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ]);
    }
}
