<?php

namespace App\Support;

use App\Contracts\ReservationCloudPushGatewayContract;
use App\Models\Reservation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HttpReservationCloudPushGateway implements ReservationCloudPushGatewayContract
{
    public function pushReservation(Reservation $reservation): array
    {
        $url = (string) config('cloud_sync.push_url', '');
        if ($url === '') {
            return [
                'ok' => false,
                'http_status' => null,
                'duplicate' => false,
                'message' => 'CLOUD_SYNC_PUSH_URL is not configured.',
            ];
        }

        $token = (string) config('cloud_sync.push_token', '');
        $reservation->loadMissing('user', 'space');

        $payload = [
            'sync_uuid' => (string) $reservation->cloud_sync_uuid,
            'reservation_number' => $reservation->reservation_number,
            'user_id' => $reservation->user_id,
            'user_email' => $reservation->user?->email,
            'space_id' => $reservation->space_id,
            'space_name' => $reservation->space?->name,
            'start_at' => $reservation->start_at?->toIso8601String(),
            'end_at' => $reservation->end_at?->toIso8601String(),
            'status' => $reservation->status,
            'purpose' => $reservation->purpose,
            'event_title' => $reservation->event_title,
            'event_description' => $reservation->event_description,
            'participant_count' => $reservation->participant_count,
            'updated_at' => $reservation->updated_at?->toIso8601String(),
        ];

        try {
            $req = Http::timeout(20)
                ->acceptJson()
                ->withHeaders([
                    'Idempotency-Key' => (string) $reservation->cloud_sync_uuid,
                ])
                ->asJson();

            if ($token !== '') {
                $req = $req->withToken($token);
            }

            $response = $req->post($url, $payload);
            $code = $response->status();

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'http_status' => $code,
                    'duplicate' => false,
                    'message' => 'Uploaded to cloud.',
                ];
            }

            if ($code === 409) {
                return [
                    'ok' => true,
                    'http_status' => $code,
                    'duplicate' => true,
                    'message' => 'Cloud already had this reservation (idempotent).',
                ];
            }

            return [
                'ok' => false,
                'http_status' => $code,
                'duplicate' => false,
                'message' => 'Cloud rejected the payload: HTTP '.$code,
            ];
        } catch (Throwable $e) {
            Log::warning('Reservation cloud push failed', [
                'reservation_id' => $reservation->id,
                'sync_uuid' => $reservation->cloud_sync_uuid,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'http_status' => null,
                'duplicate' => false,
                'message' => 'Network error: '.$e->getMessage(),
            ];
        }
    }
}
