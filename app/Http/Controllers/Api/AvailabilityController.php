<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use App\Services\TimeSlotService;
use App\Support\ApiResponse;
use App\Support\BookingSlotCutoff;
use App\Support\OperatingHalfHourSlotIterator;
use App\Support\StudentSpaceAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AvailabilityController extends Controller
{
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

        $actor = $request->user();
        if ($actor) {
            $actor->loadMissing('role');
            if ($actor->hasStudentRole()) {
                foreach ($spaces as $space) {
                    $blocked = StudentSpaceAccess::blockedMessageForSpace($space);
                    if ($blocked !== null) {
                        return response()->json(['message' => $blocked], 403);
                    }
                }
            }
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
                    ->with(['user:id,name'])
                    ->get(['id', 'user_id', 'start_at', 'end_at', 'status', 'event_title', 'event_description', 'purpose', 'reservation_number']);

            $displayName = $operational
                ? $space->scheduleOperationalDisplayName()
                : $space->userFacingName();

            $result[] = [
                'space' => array_merge($space->toArray(), [
                    'name' => $displayName,
                ]),
                'reserved_slots' => $reserved->map(fn (Reservation $r) => $this->reservedSlotPayloadForViewer(
                    $r,
                    $request->user(),
                    $operational
                )),
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

        try {
            return $this->buildPublicScheduleOverviewResponse($request);
        } catch (\Throwable $e) {
            Log::error('publicScheduleOverview failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json(['error' => 'Failed to generate schedule grid'], 500);
        }
    }

    private function buildPublicScheduleOverviewResponse(Request $request): JsonResponse
    {
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

        $physicalSpaceIds = $spaces
            ->filter(fn (Space $s) => ! $s->isConfabAssignmentPool())
            ->pluck('id')
            ->values()
            ->all();

        /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Reservation>> $occupiedBySpace */
        $occupiedBySpace = collect();
        if ($physicalSpaceIds !== []) {
            $occupiedBySpace = Reservation::query()
                ->whereIn('space_id', $physicalSpaceIds)
                ->whereIn('status', Reservation::calendarCommittedStatuses())
                ->overlapping($dayStart, $dayEnd)
                ->orderBy('start_at')
                ->get(['space_id', 'start_at', 'end_at'])
                ->groupBy('space_id');
        }

        $rows = [];
        foreach ($spaces as $space) {
            $occupied = $space->isConfabAssignmentPool()
                ? collect()
                : ($occupiedBySpace->get($space->id) ?? collect());

            $rows[] = [
                'space' => [
                    'id' => $space->id,
                    'name' => $space->userFacingName(),
                    /** Same masking as {@see userFacingName()} — never leak numbered Confab/Med Confab names on the public board. */
                    'schedule_label' => $space->userFacingName(),
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

        $operatingWindow = PolicyDocument::resolvedOperatingWindowForLocalDate($date);
        $ymd = $date->format('Y-m-d');
        try {
            $timeSlots = TimeSlotService::buildSlotsForWindow(
                $ymd,
                $operatingWindow['start'],
                $operatingWindow['end']
            );
        } catch (\Throwable) {
            $timeSlots = TimeSlotService::buildSlotsForWindow($ymd, '09:00', '17:00');
        }
        if ($timeSlots === []) {
            $timeSlots = TimeSlotService::buildSlotsForWindow($ymd, '09:00', '17:00');
        }

        return ApiResponse::data([
            'date' => $ymd,
            'timezone' => config('app.timezone'),
            'operating_hours' => [
                'day_start' => $operatingWindow['start'],
                'day_end' => $operatingWindow['end'],
            ],
            'booking_cutoff' => BookingSlotCutoff::CUTOFF_HHMM,
            'time_slots' => $timeSlots,
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

        try {
            return $this->buildPublicMonthSummaryResponse($request);
        } catch (\Throwable $e) {
            Log::error('publicMonthSummary failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json(['error' => 'Failed to generate schedule summary'], 500);
        }
    }

    private function buildPublicMonthSummaryResponse(Request $request): JsonResponse
    {
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
                ->whereIn('status', Reservation::calendarCommittedStatuses())
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
            ->whereIn('status', Reservation::calendarCommittedStatuses())
            ->where('start_at', '<', $rangeEnd)
            ->where('end_at', '>', $rangeStart)
            ->get(['space_id', 'start_at', 'end_at']);

        $activeSpaceIds = Space::query()
            ->where('is_active', true)
            ->pluck('id');

        $dates = [];
        foreach ($reservations as $r) {
            $sid = (int) $r->space_id;
            if (! $activeSpaceIds->contains($sid)) {
                continue;
            }
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
        $window = PolicyDocument::resolvedOperatingWindowForLocalDate($dayStart);
        $foundBookableWindow = false;
        $allBookableSlotsTaken = true;

        OperatingHalfHourSlotIterator::eachBoundedHalfHour(
            $window['start'],
            $window['end'],
            function (int $startM) use ($dayStart, $reservationsOnDay, &$foundBookableWindow, &$allBookableSlotsTaken): void {
                if (BookingSlotCutoff::slotStartMinutesAtOrAfterCutoff($startM)) {
                    return;
                }
                $foundBookableWindow = true;
                $slotStart = $dayStart->copy()->startOfDay()->addMinutes($startM);
                $slotEnd = $slotStart->copy()->addMinutes(30);
                if (! $this->halfHourRangeOverlapsReservation($slotStart, $slotEnd, $reservationsOnDay)) {
                    $allBookableSlotsTaken = false;
                }
            }
        );

        return $foundBookableWindow && $allBookableSlotsTaken;
    }

    /**
     * Schedule-board reserved-slot payload. Blocking times are always returned; requester-identifying
     * fields are omitted unless the viewer owns the reservation or operational disclosure is enabled
     * ({@see AvailabilityController::index} $operational flag for staff schedule views).
     *
     * @return array{id:int|string,reservation_number:?string,start_at:string,end_at:string,status:string,details_revealed:bool,title:?string,description:?string,user:?array{id:int|string,name:string}}
     */
    private function reservedSlotPayloadForViewer(Reservation $r, ?User $viewer, bool $operationalReveal): array
    {
        $viewerId = $viewer?->id;
        $isOwner = $viewerId !== null && (int) $r->user_id === (int) $viewerId;
        $revealDetails = $isOwner || $operationalReveal;

        $base = [
            'id' => $r->id,
            'reservation_number' => $r->reservation_number,
            'start_at' => $r->start_at->toIso8601String(),
            'end_at' => $r->end_at->toIso8601String(),
            'status' => $r->status,
            'details_revealed' => $revealDetails,
        ];

        if (! $revealDetails) {
            return $base + [
                'title' => null,
                'description' => null,
                'user' => null,
            ];
        }

        return $base + [
            'title' => $r->event_title,
            'description' => $r->event_description ?: $r->purpose,
            'user' => $r->user
                ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                ]
                : null,
        ];
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
