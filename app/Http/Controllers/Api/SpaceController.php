<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\JsonResponse;

class SpaceController extends Controller
{
    public function index(): JsonResponse
    {
        $spaces = Space::where('is_active', true)->orderBy('name')->get();
        return response()->json($spaces);
    }
}
