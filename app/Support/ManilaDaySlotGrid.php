<?php

namespace App\Support;

use App\Services\TimeSlotService;

/**
 * Half-hour slot grid for a Manila civil day using operating-hours open/close bounds.
 */
final class ManilaDaySlotGrid
{
    /**
     * @param  list<array{start_at: string, end_at: string}>  $occupied
     * @return list<array{hour_start: int, minute_start: int, hour_end: int, minute_end: int, available: bool, booking_cutoff_blocked: bool}>
     */
    public static function build(string $dateYmd, array $occupied, string $openHhmm, string $closeHhmm): array
    {
        $built = TimeSlotService::buildSlotsForWindow($dateYmd, $openHhmm, $closeHhmm, $occupied);

        return array_map(static fn (array $slot) => [
            'hour_start' => (int) $slot['hour_start'],
            'minute_start' => (int) $slot['minute_start'],
            'hour_end' => (int) $slot['hour_end'],
            'minute_end' => (int) $slot['minute_end'],
            'available' => (bool) $slot['is_available'],
            'booking_cutoff_blocked' => (bool) $slot['booking_cutoff_blocked'],
        ], $built);
    }
}
