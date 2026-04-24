<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Single round-trip counts for HomeDashboard "At a glance" (avoids heavy admin reservation list queries).
 */
class DashboardSummaryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('role');

        $payload = [];

        if ($user->canDo('reservation.view_own')) {
            $tz = (string) config('app.timezone');
            $payload['my_active_reservations'] = Reservation::query()
                ->where('user_id', $user->id)
                ->whereIn('status', Reservation::activeUserLimitStatuses())
                ->where('end_at', '>', Carbon::now($tz))
                ->count();
        }

        if ($user->canDo('reservation.view_all')) {
            $row = DB::table('reservations')
                ->selectRaw(
                    'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_approval, '.
                    'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as email_verification_pending, '.
                    'COUNT(*) as reservations_total',
                    [Reservation::STATUS_PENDING_APPROVAL, Reservation::STATUS_EMAIL_VERIFICATION_PENDING]
                )
                ->first();

            $payload['pending_approval'] = (int) ($row->pending_approval ?? 0);
            $payload['email_verification_pending'] = (int) ($row->email_verification_pending ?? 0);
            $payload['reservations_total'] = (int) ($row->reservations_total ?? 0);
        }

        if ($user->canDo('spaces.manage')) {
            $payload['spaces_count'] = Space::query()->count();
        }

        if ($user->canDo('users.manage')) {
            $payload['users_total'] = User::query()->count();
        }

        return ApiResponse::data($payload);
    }
}
