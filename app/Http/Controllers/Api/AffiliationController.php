<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\Office;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AffiliationController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::data([
            'colleges' => College::query()->orderBy('name')->get(['id', 'name']),
            'offices' => Office::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}

