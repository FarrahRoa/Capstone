<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReservationCloudSyncService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloudSyncController extends Controller
{
    public function status(ReservationCloudSyncService $service): JsonResponse
    {
        return ApiResponse::data($service->buildStatusSnapshot());
    }

    public function upload(Request $request, ReservationCloudSyncService $service): JsonResponse
    {
        $result = $service->runManualUpload((int) $request->user()->id);

        $ok = $result['failed'] === 0;

        return ApiResponse::message(
            $ok
                ? 'Manual upload finished.'
                : 'Manual upload finished with one or more failures.',
            [
                'processed' => $result['processed'],
                'succeeded' => $result['succeeded'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'],
                'messages' => $result['messages'],
                'status' => $service->buildStatusSnapshot(),
            ],
            $ok ? 200 : 422
        );
    }
}
