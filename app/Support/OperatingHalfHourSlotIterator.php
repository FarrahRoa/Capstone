<?php

namespace App\Support;

use App\Services\TimeSlotService;
use Carbon\Carbon;

/**
 * Bounded half-hour slot iteration — delegates to {@see TimeSlotService} (strict FOR loop).
 */
final class OperatingHalfHourSlotIterator
{
    public const MAX_HALF_HOUR_SLOTS_PER_DAY = TimeSlotService::MAX_SLOTS_PER_DAY;

    /**
     * @return array{0: int, 1: int}|null [openMinutes, closeMinutes] or null when invalid/unsafe
     */
    public static function resolveOpenCloseMinutes(string $openHhmm, string $closeHhmm): ?array
    {
        $openM = BookingSlotCutoff::wallClockToMinutes($openHhmm);
        $closeM = BookingSlotCutoff::wallClockToMinutes($closeHhmm);

        if ($closeM <= $openM) {
            return null;
        }

        if (($closeM - $openM) > 24 * 60) {
            return null;
        }

        return [$openM, $closeM];
    }

    /**
     * @param  callable(int $startMinute, int $endMinute): void  $callback
     */
    public static function eachBoundedHalfHour(string $openHhmm, string $closeHhmm, callable $callback): void
    {
        $bounds = self::resolveOpenCloseMinutes($openHhmm, $closeHhmm);
        if ($bounds === null) {
            return;
        }

        $slots = TimeSlotService::buildSlotsForWindow('2000-01-01', $openHhmm, $closeHhmm);

        foreach ($slots as $slot) {
            $startM = ((int) $slot['hour_start']) * 60 + (int) $slot['minute_start'];
            $callback($startM, $startM + 30);
        }
    }

    /**
     * @param  callable(Carbon $slotStart, Carbon $slotEnd, bool $cutoffBlocked): void  $callback
     */
    public static function eachBoundedHalfHourOnDay(
        string $dateYmd,
        string $openHhmm,
        string $closeHhmm,
        string $tz,
        callable $callback
    ): void {
        $slots = TimeSlotService::buildSlotsForWindow($dateYmd, $openHhmm, $closeHhmm);

        foreach ($slots as $slot) {
            $slotStart = Carbon::parse(
                sprintf('%s %02d:%02d:00', $dateYmd, (int) $slot['hour_start'], (int) $slot['minute_start']),
                $tz
            );
            $slotEnd = Carbon::parse(
                sprintf('%s %02d:%02d:00', $dateYmd, (int) $slot['hour_end'], (int) $slot['minute_end']),
                $tz
            );
            $callback($slotStart, $slotEnd, (bool) $slot['booking_cutoff_blocked']);
        }
    }
}
