<?php

namespace App\Services;

use App\Contracts\ReservationCloudPushGatewayContract;
use App\Models\CloudSyncEvent;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class ReservationCloudSyncService
{
    public function __construct(
        private readonly ReservationCloudPushGatewayContract $pushGateway,
    ) {}

    /**
     * Reservations created on the fallback instance that still need upload (or were edited after last upload).
     *
     * @return \Illuminate\Database\Eloquent\Builder<Reservation>
     */
    public function pendingUploadQuery()
    {
        return Reservation::query()
            ->where('cloud_sync_origin', Reservation::CLOUD_SYNC_ORIGIN_LOCAL_FALLBACK)
            ->whereRaw('(cloud_synced_at IS NULL OR updated_at > cloud_synced_at)');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStatusSnapshot(): array
    {
        $pending = (int) $this->pendingUploadQuery()->count();
        $reachUrl = (string) config('cloud_sync.reachability_url', '');
        $pushUrl = (string) config('cloud_sync.push_url', '');

        $reachable = null;
        if ($reachUrl !== '') {
            try {
                $reachable = Http::timeout(5)->head($reachUrl)->successful();
            } catch (\Throwable) {
                $reachable = false;
            }
        }

        $autoEnabled = (bool) config('cloud_sync.auto_sync_enabled', false);
        $automaticState = $autoEnabled ? 'idle' : 'disabled';
        $automaticNote = $autoEnabled
            ? 'Automatic sync is enabled in configuration, but no background worker ships with this app yet (manual upload remains available).'
            : 'Automatic sync is not enabled (set CLOUD_SYNC_AUTO_ENABLED=true when a worker is available).';

        $lastSuccess = CloudSyncEvent::query()
            ->where('event_type', CloudSyncEvent::TYPE_RESERVATION_PUSH_SUCCESS)
            ->orderByDesc('id')
            ->first();

        $lastFailure = CloudSyncEvent::query()
            ->where('event_type', CloudSyncEvent::TYPE_RESERVATION_PUSH_FAILED)
            ->orderByDesc('id')
            ->first();

        $recentEvents = CloudSyncEvent::query()
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'reservation_id', 'event_type', 'status', 'summary', 'created_at'])
            ->map(fn (CloudSyncEvent $e) => [
                'id' => $e->id,
                'reservation_id' => $e->reservation_id,
                'event_type' => $e->event_type,
                'status' => $e->status,
                'summary' => $e->summary,
                'created_at' => $e->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'record_origin' => (string) config('cloud_sync.record_origin', 'primary'),
            'cloud_reachable' => $reachable,
            'push_url_configured' => $pushUrl !== '',
            'reachability_url_configured' => $reachUrl !== '',
            'pending_local_changes' => $pending,
            'automatic_sync' => [
                'state' => $automaticState,
                'enabled_flag' => $autoEnabled,
                'note' => $automaticNote,
            ],
            'last_success' => $lastSuccess ? [
                'at' => $lastSuccess->created_at?->toIso8601String(),
                'summary' => $lastSuccess->summary,
            ] : null,
            'last_failure' => $lastFailure ? [
                'at' => $lastFailure->created_at?->toIso8601String(),
                'summary' => $lastFailure->summary,
            ] : null,
            'recent_events' => $recentEvents,
            'cloud_change_feed' => [
                'available' => false,
                'message' => 'Inbound cloud change detection is not implemented in this build.',
                'items' => [],
            ],
        ];
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int, skipped: int, messages: list<string>}
     */
    public function runManualUpload(int $actorUserId): array
    {
        CloudSyncEvent::create([
            'reservation_id' => null,
            'event_type' => CloudSyncEvent::TYPE_MANUAL_UPLOAD_START,
            'status' => 'info',
            'summary' => 'Manual upload started by user #'.$actorUserId,
            'context' => [],
        ]);

        $pending = $this->pendingUploadQuery()->orderBy('id')->get();
        $succeeded = 0;
        $failed = 0;
        $skipped = 0;
        $messages = [];

        foreach ($pending as $reservation) {
            $result = $this->pushGateway->pushReservation($reservation);

            if ($result['ok']) {
                DB::table('reservations')->where('id', $reservation->id)->update([
                    'cloud_synced_at' => now(),
                ]);
                $succeeded++;
                CloudSyncEvent::create([
                    'reservation_id' => $reservation->id,
                    'event_type' => CloudSyncEvent::TYPE_RESERVATION_PUSH_SUCCESS,
                    'status' => 'success',
                    'summary' => 'Reservation #'.$reservation->id.' uploaded'.($result['duplicate'] ? ' (duplicate key treated as success)' : '').'.',
                    'context' => [
                        'sync_uuid' => $reservation->cloud_sync_uuid,
                        'http_status' => $result['http_status'],
                        'duplicate' => $result['duplicate'],
                    ],
                ]);
                $messages[] = 'Reservation #'.$reservation->id.': '.$result['message'];
            } else {
                if (str_contains($result['message'], 'CLOUD_SYNC_PUSH_URL')) {
                    $skipped++;
                    CloudSyncEvent::create([
                        'reservation_id' => $reservation->id,
                        'event_type' => CloudSyncEvent::TYPE_RESERVATION_PUSH_SKIPPED,
                        'status' => 'skipped',
                        'summary' => 'Reservation #'.$reservation->id.' not pushed: '.$result['message'],
                        'context' => ['sync_uuid' => $reservation->cloud_sync_uuid],
                    ]);
                } else {
                    $failed++;
                    CloudSyncEvent::create([
                        'reservation_id' => $reservation->id,
                        'event_type' => CloudSyncEvent::TYPE_RESERVATION_PUSH_FAILED,
                        'status' => 'failed',
                        'summary' => 'Reservation #'.$reservation->id.' failed: '.$result['message'],
                        'context' => [
                            'sync_uuid' => $reservation->cloud_sync_uuid,
                            'http_status' => $result['http_status'],
                        ],
                    ]);
                }
                $messages[] = 'Reservation #'.$reservation->id.': '.$result['message'];
            }
        }

        return [
            'processed' => $pending->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'skipped' => $skipped,
            'messages' => $messages,
        ];
    }
}
