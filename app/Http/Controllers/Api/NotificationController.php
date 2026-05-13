<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = $request->user()
            ->notifications()
            ->latest()
            ->take(50)
            ->get();

        $data = $rows->map(fn ($n) => [
            'id' => $n->id,
            'read_at' => $n->read_at,
            'created_at' => $n->created_at,
            'data' => $n->data,
        ])->all();

        return ApiResponse::data($data);
    }
}
