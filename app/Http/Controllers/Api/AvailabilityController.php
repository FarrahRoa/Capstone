<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Space;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AvailabilityController extends Controller
{
    private const BOOKING_DAY_START_HOUR = 9;

    private const BOOKING_DAY_END_HOUR = 18;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'space_id' => 'required_without:date|nullable|exists:spaces,id',
            'date' => 'required|date',
            'operational' => 'sometimes|boolean',
        ]);

        $operational = $request->boolean('operational')
            && $request->user()
            && $request->user()->canDo('reservation.view_all');

        // Explicit app timezone: booking day boundaries match config (Asia/Manila by default).
        $date = Carbon::parse($request->input('date'), config('app.timezone'))->startOfDay();
        $spaceId = $request->input('space_id');

        $spaces = $spaceId
            ? Space::where('id', $spaceId)->where('is_active', true)->get()
            : Space::where('is_active', true)->orderBy('name')->get();

        if ($spaces->isEmpty()) {
            return response()->json(['message' => 'No spaces found.'], 404);
        }

        $result = [];
        foreach ($spaces as $space) {
            $dayStart = $date->copy();
            $dayEnd = $date->copy()->endOfDay()->addSecond();

            // Confab assignment pool: many pending requests can share the same slot; do not grey out slots here.
            $reserved = $space->isConfabAssignmentPool()
                ? collect()
                : Reservation::where('space_id', $space->id)
                    ->blocking()
                    ->overlapping($dayStart, $dayEnd)
                    ->orderBy('start_at')
                    ->get(['id', 'start_at', 'end_at', 'status']);

            $displayName = $operational
                ? $space->scheduleOperationalDisplayName()
                : $space->userFacingName();

            $result[] = [
                'space' => array_merge($space->toArray(), [
                    'name' => $displayName,
                ]),
                'reserved_slots' => $reserved->map(fn ($r) => [
                    'id' => $r->id,
                    'start_at' => $r->start_at->toIso8601String(),
                    'end_at' => $r->end_at->toIso8601String(),
                    'status' => $r->status,
                ]),
            ];
        }

        return ApiResponse::data($result);
    }

    /**
     * Read-only day schedule for unauthenticated visitors (e.g. login page).
     * Occupied times reflect approved reservations only — pending approval does not block publicly.
     */
    public function publicScheduleOverview(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'space_id' => 'nullable|exists:spaces,id',
        ]);

        $date = Carbon::parse($request->input('date'), config('app.timezone'))->startOfDay();
        $spaceId = $request->input('space_id');

        $spaces = $spaceId
            ? Space::where('id', $spaceId)->where('is_active', true)->get()
            : Space::where('is_active', true)->orderBy('name')->get();

        if ($spaces->isEmpty()) {
            return response()->json(['message' => 'No spaces found.'], 404);
        }

        $dayStart = $date->copy();
        $dayEnd = $date->copy()->endOfDay()->addSecond();

        $rows = [];
        foreach ($spaces as $space) {
            $occupied = $space->isConfabAssignmentPool()
                ? collect()
                : Reservation::query()
                    ->where('space_id', $space->id)
                    ->where('status', Reservation::STATUS_APPROVED)
                    ->overlapping($dayStart, $dayEnd)
                    ->orderBy('start_at')
                    ->get(['start_at', 'end_at']);

            $rows[] = [
                'space' => [
                    'id' => $space->id,
                    'name' => $space->userFacingName(),
                    'type' => $space->type,
                    'slug' => $space->slug,
                    'is_confab_pool' => (bool) $space->is_confab_pool,
                ],
                'occupied_slots' => $occupied->map(fn ($r) => [
                    'start_at' => $r->start_at->toIso8601String(),
                    'end_at' => $r->end_at->toIso8601String(),
                ])->values()->all(),
            ];
        }

        return ApiResponse::data([
            'date' => $date->format('Y-m-d'),
            'timezone' => config('app.timezone'),
            'day_start_hour' => self::BOOKING_DAY_START_HOUR,
            'day_end_hour' => self::BOOKING_DAY_END_HOUR,
            'spaces' => $rows,
        ]);
    }

    /**
     * Public month summary: fully-booked dates are computed from approved reservations only.
     * This prevents pending workflow data from affecting the public calendar preview.
     */
    public function publicMonthSummary(Request $request): JsonResponse
    {
        $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $space = Space::query()->where('id', $request->integer('space_id'))->where('is_active', true)->first();
        if (!$space) {
            return response()->json(['message' => 'Space not found.'], 404);
        }

        $tz = config('app.timezone');
        $from = Carbon::parse($request->input('from'), $tz)->startOfDay();
        $to = Carbon::parse($request->input('to'), $tz)->startOfDay();
        if ($from->diffInDays($to) > 45) {
            return response()->json(['message' => 'Date range too large.'], 422);
        }

        $rangeStart = $from->copy()->startOfDay();
        $rangeEnd = $to->copy()->endOfDay()->addSecond();

        $allReservations = $space->isConfabAssignmentPool()
            ? collect()
            : Reservation::query()
                ->where('space_id', $space->id)
                ->where('status', Reservation::STATUS_APPROVED)
                ->where('start_at', '<', $rangeEnd)
                ->where('end_at', '>', $rangeStart)
                ->orderBy('start_at')
                ->get(['start_at', 'end_at']);

        $fullyBookedDates = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay()->addSecond();
            $onDay = $allReservations->filter(
                fn ($r) => $r->start_at->lt($dayEnd) && $r->end_at->gt($dayStart)
            );
            if ($this->isDayFullyBookedForSpace($dayStart, $onDay)) {
                $fullyBookedDates[] = $dayStart->format('Y-m-d');
            }
        }

        return ApiResponse::data([
            'fully_booked_dates' => $fullyBookedDates,
        ]);
    }

    /**
     * Public month overview: which spaces have at least one approved reservation on each day.
     * Keeps the overview chips consistent with the public login-page schedule board.
     */
    public function publicMonthOverview(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $tz = config('app.timezone');
        $from = Carbon::parse($request->input('from'), $tz)->startOfDay();
        $to = Carbon::parse($request->input('to'), $tz)->startOfDay();
        if ($from->diffInDays($to) > 45) {
            return response()->json(['message' => 'Date range too large.'], 422);
        }

        $rangeStart = $from->copy()->startOfDay();
        $rangeEnd = $to->copy()->endOfDay()->addSecond();

        /** @var Collection<int, Reservation> $reservations */
        $reservations = Reservation::query()
            ->where('status', Reservation::STATUS_APPROVED)
            ->where('start_at', '<', $rangeEnd)
            ->where('end_at', '>', $rangeStart)
            ->get(['space_id', 'start_at', 'end_at']);

        $dates = [];
        foreach ($reservations as $r) {
            $start = $r->start_at->copy()->timezone($tz)->startOfDay();
            $end = $r->end_at->copy()->timezone($tz)->startOfDay();
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                if ($day->lt($from) || $day->gt($to)) {
                    continue;
                }
                $ymd = $day->format('Y-m-d');
                if (!isset($dates[$ymd])) {
                    $dates[$ymd] = [];
                }
                $sid = (int) $r->space_id;
                $dates[$ymd][$sid] = true;
            }
        }

        $out = [];
        foreach ($dates as $ymd => $set) {
            $ids = array_map('intval', array_keys($set));
            sort($ids);
            $out[$ymd] = $ids;
        }
        ksort($out);

        return ApiResponse::data([
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'dates' => $out,
        ]);
    }

    /**
     * For a space and inclusive date range, list civil dates (Y-m-d) where every bookable
     * half-hour slot (09:00–09:30 … 17:30–18:00 app timezone) overlaps a blocking reservation.
     * Matches frontend {@see buildManilaHalfHourSlots} / AvailabilityController::index day logic.
     */
    public function monthSummary(Request $request): JsonResponse
    {
        $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $space = Space::query()->where('id', $request->integer('space_id'))->where('is_active', true)->first();
        if (!$space) {
            return response()->json(['message' => 'Space not found.'], 404);
        }

        $tz = config('app.timezone');
        $from = Carbon::parse($request->input('from'), $tz)->startOfDay();
        $to = Carbon::parse($request->input('to'), $tz)->startOfDay();
        if ($from->diffInDays($to) > 45) {
            return response()->json(['message' => 'Date range too large.'], 422);
        }

        $rangeStart = $from->copy()->startOfDay();
        $rangeEnd = $to->copy()->endOfDay()->addSecond();

        $allReservations = $space->isConfabAssignmentPool()
            ? collect()
            : Reservation::query()
                ->where('space_id', $space->id)
                ->blocking()
                ->where('start_at', '<', $rangeEnd)
                ->where('end_at', '>', $rangeStart)
                ->orderBy('start_at')
                ->get(['start_at', 'end_at']);

        $fullyBookedDates = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay()->addSecond();
            $onDay = $allReservations->filter(
                fn ($r) => $r->start_at->lt($dayEnd) && $r->end_at->gt($dayStart)
            );
            if ($this->isDayFullyBookedForSpace($dayStart, $onDay)) {
                $fullyBookedDates[] = $dayStart->format('Y-m-d');
            }
        }

        return ApiResponse::data([
            'fully_booked_dates' => $fullyBookedDates,
        ]);
    }

    /**
     * Overview markers for a date range: which spaces have at least one blocking reservation on each day.
     * Intended for calendar "overview mode" (color chips per day), not for slot-level availability.
     *
     * Response shape:
     * - from/to: echo inputs (Y-m-d)
     * - dates: map of Y-m-d -> array<int space_id>
     */
    public function monthOverview(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $tz = config('app.timezone');
        $from = Carbon::parse($request->input('from'), $tz)->startOfDay();
        $to = Carbon::parse($request->input('to'), $tz)->startOfDay();
        if ($from->diffInDays($to) > 45) {
            return response()->json(['message' => 'Date range too large.'], 422);
        }

        $rangeStart = $from->copy()->startOfDay();
        $rangeEnd = $to->copy()->endOfDay()->addSecond();

        /** @var Collection<int, Reservation> $reservations */
        $reservations = Reservation::query()
            ->blocking()
            ->where('start_at', '<', $rangeEnd)
            ->where('end_at', '>', $rangeStart)
            ->get(['space_id', 'start_at', 'end_at']);

        $dates = [];
        foreach ($reservations as $r) {
            // Fixed-slot product rule means reservations stay within a single civil day,
            // but we keep this robust in case policy ever expands.
            $start = $r->start_at->copy()->timezone($tz)->startOfDay();
            $end = $r->end_at->copy()->timezone($tz)->startOfDay();
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                if ($day->lt($from) || $day->gt($to)) {
                    continue;
                }
                $ymd = $day->format('Y-m-d');
                if (!isset($dates[$ymd])) {
                    $dates[$ymd] = [];
                }
                $sid = (int) $r->space_id;
                $dates[$ymd][$sid] = true;
            }
        }

        // Convert set maps to stable numeric arrays.
        $out = [];
        foreach ($dates as $ymd => $set) {
            $ids = array_map('intval', array_keys($set));
            sort($ids);
            $out[$ymd] = $ids;
        }
        ksort($out);

        return ApiResponse::data([
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'dates' => $out,
        ]);
    }

    /**
     * @param  Collection<int, Reservation>  $reservationsOnDay
     */
    private function isDayFullyBookedForSpace(Carbon $dayStart, Collection $reservationsOnDay): bool
    {
        for ($h = self::BOOKING_DAY_START_HOUR; $h < self::BOOKING_DAY_END_HOUR; $h++) {
            foreach ([0, 30] as $minute) {
                $slotStart = $dayStart->copy()->setTime($h, $minute, 0);
                $slotEnd = $minute === 0
                    ? $dayStart->copy()->setTime($h, 30, 0)
                    : $dayStart->copy()->setTime($h + 1, 0, 0);
                if (! $this->halfHourRangeOverlapsReservation($slotStart, $slotEnd, $reservationsOnDay)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     */
    private function halfHourRangeOverlapsReservation(Carbon $slotStart, Carbon $slotEnd, Collection $reservations): bool
    {
        foreach ($reservations as $r) {
            if ($slotStart->lt($r->end_at) && $slotEnd->gt($r->start_at)) {
                return true;
            }
        }

        return false;
    }
}
