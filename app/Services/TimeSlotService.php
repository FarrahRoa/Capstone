<?php

namespace App\Services;

use App\Models\PolicyDocument;
use App\Support\BookingSlotCutoff;
use App\Support\ReservationLeadTimePolicy;
use Carbon\Carbon;

/**
 * Fail-safe half-hour slot grid for operating hours (Asia/Manila).
 * Uses a strict FOR loop — no open-ended while loops.
 */
final class TimeSlotService
{
    public const MAX_SLOTS_PER_DAY = 48;

    /** Fallback slot count when bounds are invalid (~12 hours). */
    public const FALLBACK_SLOT_COUNT = 24;

    /**
     * @param  list<array{start_at: string, end_at: string}>|null  $occupied
     * @return list<array{
     *   time: string,
     *   value: string,
     *   hour_start: int,
     *   minute_start: int,
     *   hour_end: int,
     *   minute_end: int,
     *   is_available: bool,
     *   status: string,
     *   booking_cutoff_blocked: bool
     * }>
     */
    public static function buildSlotsForWindow(
        string $dateYmd,
        string $openHhmm,
        string $closeHhmm,
        ?array $occupied = null
    ): array {
        $tz = (string) config('app.timezone', ReservationLeadTimePolicy::TZ);
        $open = PolicyDocument::normalizeWallClockHhmm($openHhmm, '06:00');
        $close = PolicyDocument::normalizeWallClockHhmm($closeHhmm, '18:30');

        try {
            $windowStart = Carbon::parse($dateYmd.' '.$open, $tz);
            $windowEnd = Carbon::parse($dateYmd.' '.$close, $tz);
        } catch (\Throwable) {
            return self::buildFallbackSlots($dateYmd, $occupied, $tz);
        }

        if (! $windowEnd->gt($windowStart)) {
            return self::buildFallbackSlots($dateYmd, $occupied, $tz);
        }

        $totalMinutes = $windowStart->diffInMinutes($windowEnd);
        $totalSlots = (int) ceil($totalMinutes / 30);

        if ($totalSlots > self::MAX_SLOTS_PER_DAY || $totalSlots <= 0) {
            return self::buildFallbackSlots($dateYmd, $occupied, $tz);
        }

        $cutoffAnchor = Carbon::parse($dateYmd.' '.BookingSlotCutoff::CUTOFF_HHMM, $tz);
        $occupied = $occupied ?? [];
        $slots = [];
        $currentPointer = $windowStart->copy();

        for ($i = 0; $i < $totalSlots; $i++) {
            $slotEndPointer = $currentPointer->copy()->addMinutes(30);
            if ($slotEndPointer->gt($windowEnd)) {
                break;
            }

            $isCutoff = $currentPointer->greaterThanOrEqualTo($cutoffAnchor);
            $busy = self::rangeOverlapsOccupied($currentPointer, $slotEndPointer, $occupied);
            $status = $isCutoff ? 'unavailable_cutoff' : ($busy ? 'occupied' : 'available');

            $slots[] = [
                'time' => $currentPointer->format('g:i A'),
                'value' => $currentPointer->format('H:i'),
                'hour_start' => $currentPointer->hour,
                'minute_start' => $currentPointer->minute,
                'hour_end' => $slotEndPointer->hour,
                'minute_end' => $slotEndPointer->minute,
                'is_available' => ! $isCutoff && ! $busy,
                'status' => $status,
                'booking_cutoff_blocked' => $isCutoff,
            ];

            $currentPointer->addMinutes(30);
        }

        return $slots;
    }

    /**
     * @param  list<array{start_at: string, end_at: string}>|null  $occupied
     * @return list<array<string, mixed>>
     */
    private static function buildFallbackSlots(string $dateYmd, ?array $occupied, string $tz): array
    {
        return self::buildSlotsForWindow($dateYmd, '09:00', '17:00', $occupied);
    }

    /**
     * @param  list<array{start_at: string, end_at: string}>  $occupied
     */
    private static function rangeOverlapsOccupied(Carbon $slotStart, Carbon $slotEnd, array $occupied): bool
    {
        foreach ($occupied as $row) {
            try {
                $rs = Carbon::parse((string) $row['start_at'], $slotStart->timezoneName);
                $re = Carbon::parse((string) $row['end_at'], $slotStart->timezoneName);
            } catch (\Throwable) {
                continue;
            }
            if ($slotStart->lt($re) && $slotEnd->gt($rs)) {
                return true;
            }
        }

        return false;
    }
}
