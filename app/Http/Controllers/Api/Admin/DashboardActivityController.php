<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReservationLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardActivityController extends Controller
{
    /**
     * Recent reservation audit rows for admin dashboard monitoring (real reservation_logs only).
     */
    public function recentReservationLogs(): JsonResponse
    {
        $logs = ReservationLog::query()
            ->with([
                'reservation:id,user_id,space_id,status,start_at,end_at',
                'reservation.space:id,name',
                'reservation.user:id,name,email',
                'actor:id,name,email',
            ])
            ->latest('created_at')
            ->limit(20)
            ->get([
                'id',
                'reservation_id',
                'actor_user_id',
                'actor_type',
                'action',
                'notes',
                'created_at',
            ]);

        $payload = $logs->map(function (ReservationLog $log) {
            $r = $log->reservation;

            return [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => ReservationLog::actionLabel((string) $log->action),
                'actor_type' => $log->actor_type,
                'created_at' => $log->created_at?->toISOString(),
                'notes' => $log->notes,
                'reservation_id' => $r?->id,
                'requester' => $r?->user ? [
                    'name' => $r->user->name,
                    'email' => $r->user->email,
                ] : null,
                'space' => $r?->space ? [
                    'name' => $r->space->name,
                ] : null,
                'slot' => $r && $r->start_at && $r->end_at ? [
                    'start_at' => $r->start_at->toISOString(),
                    'end_at' => $r->end_at->toISOString(),
                ] : null,
                'actor' => $log->actor ? [
                    'name' => $log->actor->name,
                    'email' => $log->actor->email,
                ] : null,
            ];
        });

        return ApiResponse::data($payload);
    }
}
