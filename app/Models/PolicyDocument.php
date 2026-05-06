<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyDocument extends Model
{
    public const SLUG_RESERVATION_GUIDELINES = 'reservation_guidelines';

    /** Rich text shown when users choose the general Confab (assignment pool), before a specific room exists. */
    public const SLUG_CONFAB_RESERVATION_GUIDELINES = 'confab_reservation_guidelines';

    public const SLUG_OPERATING_HOURS = 'reservation_operating_hours';

    protected $fillable = ['slug', 'content'];

    public static function reservationGuidelines(): self
    {
        return self::firstOrCreate(
            ['slug' => self::SLUG_RESERVATION_GUIDELINES],
            ['content' => self::defaultReservationGuidelines()]
        );
    }

    public static function confabReservationGuidelines(): self
    {
        return self::firstOrCreate(
            ['slug' => self::SLUG_CONFAB_RESERVATION_GUIDELINES],
            ['content' => self::defaultConfabReservationGuidelines()]
        );
    }

    public static function defaultReservationGuidelines(): string
    {
        return implode("\n\n", [
            'Reservations are subject to library approval.',
            'Confirm your XU email promptly after booking; unconfirmed requests may be cancelled.',
            'Some rooms (Medical Confab, Boardroom) require special eligibility set by administrators.',
            'Use library spaces respectfully and follow posted room rules.',
        ]);
    }

    public static function defaultConfabReservationGuidelines(): string
    {
        return implode("\n\n", [
            'General Confab requests use one shared booking type. You are not choosing a specific numbered Confab room yet.',
            'When librarians approve your request, they assign Confab 1, Confab 2, or another suitable room based on availability and your needs.',
            'Compare the numbered Confab rooms below to see differences in location, capacity, and equipment before you submit.',
        ]);
    }

    public static function operatingHours(): self
    {
        return self::firstOrCreate(
            ['slug' => self::SLUG_OPERATING_HOURS],
            ['content' => json_encode(self::defaultOperatingHours(), JSON_UNESCAPED_SLASHES)]
        );
    }

    /**
     * @return array{day_start: string, day_end: string, weekend_day_start: ?string, weekend_day_end: ?string}
     */
    public static function defaultOperatingHours(): array
    {
        return [
            'day_start' => '06:00',
            'day_end' => '18:30',
            'weekend_day_start' => null,
            'weekend_day_end' => null,
        ];
    }

    /**
     * @return array{day_start: string, day_end: string, weekend_day_start: ?string, weekend_day_end: ?string}
     */
    public static function decodedOperatingHours(): array
    {
        $doc = self::operatingHours();
        $hours = json_decode((string) $doc->content, true);
        if (! is_array($hours)) {
            $hours = self::defaultOperatingHours();
        }
        $def = self::defaultOperatingHours();

        $ws = $hours['weekend_day_start'] ?? null;
        $we = $hours['weekend_day_end'] ?? null;
        $ws = is_string($ws) && $ws !== '' ? $ws : null;
        $we = is_string($we) && $we !== '' ? $we : null;
        if ($ws === null || $we === null) {
            $ws = null;
            $we = null;
        }

        return [
            'day_start' => (string) ($hours['day_start'] ?? $def['day_start']),
            'day_end' => (string) ($hours['day_end'] ?? $def['day_end']),
            'weekend_day_start' => $ws,
            'weekend_day_end' => $we,
        ];
    }

    /**
     * Open/close wall times (H:i) for this local calendar day in the app timezone.
     *
     * @return array{start: string, end: string}
     */
    public static function resolvedOperatingWindowForLocalDate(Carbon $localDateInTz): array
    {
        $hours = self::decodedOperatingHours();
        if ($localDateInTz->isWeekend()
            && $hours['weekend_day_start'] !== null
            && $hours['weekend_day_end'] !== null) {
            return [
                'start' => $hours['weekend_day_start'],
                'end' => $hours['weekend_day_end'],
            ];
        }

        return [
            'start' => $hours['day_start'],
            'end' => $hours['day_end'],
        ];
    }

    /**
     * True if some portion of [start, end) falls outside daily open/close windows (per Manila/app tz calendar day).
     */
    public static function reservationOutsideOperatingHours(Carbon $start, Carbon $end, string $tz): bool
    {
        $start = $start->copy()->timezone($tz);
        $end = $end->copy()->timezone($tz);
        if ($end->lte($start)) {
            return true;
        }

        $day = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        while ($day->lte($lastDay)) {
            $win = self::resolvedOperatingWindowForLocalDate($day);
            try {
                $open = $day->copy()->setTimeFromTimeString($win['start']);
                $close = $day->copy()->setTimeFromTimeString($win['end']);
            } catch (\Throwable) {
                return true;
            }

            if ($close->lte($open)) {
                return true;
            }

            $dayStart = $day->copy()->startOfDay();
            $nextMidnight = $day->copy()->addDay()->startOfDay();
            $overlapStart = $start->copy()->max($dayStart);
            $overlapEnd = $end->copy()->min($nextMidnight);

            if ($overlapStart->lt($overlapEnd)) {
                if ($overlapStart->lt($open) || $overlapEnd->gt($close)) {
                    return true;
                }
            }

            $day->addDay();
        }

        return false;
    }
}
