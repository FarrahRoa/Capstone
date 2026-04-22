<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Consistent JSON success envelopes for the public API (Phase 10).
 *
 * - Single resource / non-paginated collection: { "data": ... }
 * - Message + payload: { "message": "...", "data": ... }
 * Paginated list endpoints keep Laravel's native LengthAwarePaginator JSON shape.
 */
final class ApiResponse
{
    public static function data(mixed $payload, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $payload], $status);
    }

    /**
     * @param  array|object  $payload  Resource payload under "data"
     */
    public static function message(string $message, mixed $payload = null, int $status = 200): JsonResponse
    {
        $body = ['message' => $message];
        if ($payload !== null) {
            $body['data'] = $payload;
        }

        return response()->json($body, $status);
    }
}
