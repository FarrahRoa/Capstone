<?php

namespace App\Models;

use App\Support\BookingSlotCutoff;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
     * @return array{day_start: string, day_end: string, weekend_day_start: ?string, weekend_day_end: ?string, max_booking_date: ?string}
     */
    public static function defaultOperatingHours(): array
    {
        return [
            'day_start' => '06:00',
            'day_end' => '18:30',
            'weekend_day_start' => null,
            'weekend_day_end' => null,
            'max_booking_date' => null,
        ];
    }

    /**
     * @return array{day_start: string, day_end: string, weekend_day_start: ?string, weekend_day_end: ?string, max_booking_date: ?string}
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

        $maxBookingDate = $hours['max_booking_date'] ?? null;
        $maxBookingDate = is_string($maxBookingDate) && $maxBookingDate !== '' ? $maxBookingDate : null;
        if ($maxBookingDate !== null) {
            try {
                $maxBookingDate = Carbon::parse($maxBookingDate, (string) config('app.timezone'))->format('Y-m-d');
            } catch (\Throwable) {
                $maxBookingDate = null;
            }
        }

        return [
            'day_start' => self::normalizeWallClockHhmm($hours['day_start'] ?? null, $def['day_start']),
            'day_end' => self::normalizeWallClockHhmm($hours['day_end'] ?? null, $def['day_end']),
            'weekend_day_start' => $ws !== null ? self::normalizeWallClockHhmm($ws, $def['day_start']) : null,
            'weekend_day_end' => $we !== null ? self::normalizeWallClockHhmm($we, $def['day_end']) : null,
            'max_booking_date' => $maxBookingDate,
        ];
    }

    /**
     * Normalize admin-entered wall times (e.g. "06:00 am", "6:30 PM") to 24h H:i for comparisons.
     */
    public static function normalizeWallClockHhmm(mixed $raw, string $fallback = '06:00'): string
    {
        if (! is_string($raw)) {
            return $fallback;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return $fallback;
        }

        $tz = (string) config('app.timezone', 'Asia/Manila');

        try {
            return Carbon::parse('2000-01-01 '.$trimmed, $tz)->format('H:i');
        } catch (\Throwable) {
            try {
                return Carbon::parse($trimmed, $tz)->format('H:i');
            } catch (\Throwable) {
                if (preg_match('/^(\d{1,2}):(\d{2})/', $trimmed, $m)) {
                    return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
                }

                return $fallback;
            }
        }
    }

    /**
     * True when any portion of the reservation falls on a Manila civil day after max_booking_date.
     */
    public static function reservationBeyondMaxBookingDate($start, $end, string $tz): bool
    {
        $maxYmd = self::decodedOperatingHours()['max_booking_date'] ?? null;
        if (! is_string($maxYmd) || $maxYmd === '') {
            return false;
        }

        try {
            $start = $start instanceof Carbon ? $start : Carbon::parse((string) $start, $tz);
            $end = $end instanceof Carbon ? $end : Carbon::parse((string) $end, $tz);
        } catch (\Throwable) {
            return true;
        }

        $startDay = $start->copy()->timezone($tz)->format('Y-m-d');
        $endDay = $end->copy()->timezone($tz)->format('Y-m-d');

        return $startDay > $maxYmd || $endDay > $maxYmd;
    }

    public static function maxBookingDateValidationMessage(): string
    {
        $maxYmd = self::decodedOperatingHours()['max_booking_date'] ?? null;
        if (! is_string($maxYmd) || $maxYmd === '') {
            return 'Reservations cannot be made beyond the configured booking window.';
        }

        try {
            $label = Carbon::parse($maxYmd, (string) config('app.timezone'))->format('F j, Y');
        } catch (\Throwable) {
            $label = $maxYmd;
        }

        return "Reservations cannot be made beyond {$label}.";
    }

    /**
     * Open/close wall times (H:i) for this local calendar day in the app timezone.
     *
     * @return array{start: string, end: string}
     */
    public static function resolvedOperatingWindowForLocalDate(CarbonInterface $localDateInTz): array
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

    public const SUBMISSION_CUTOFF_RESUME_HOUR = 9;

    public const SUBMISSION_CUTOFF_RESUME_MINUTE = 0;

    /**
     * True when new reservation submissions are closed (4:30 PM–9:00 AM Manila/app tz wall clock).
     * Uses current server time only — never reservation start/end instants.
     */
    public static function isPastReservationCutoff(?CarbonInterface $now = null): bool
    {
        $tz = (string) config('app.timezone');
        $now = ($now ?? Carbon::now($tz))->copy()->timezone($tz);
        $minutes = $now->hour * 60 + $now->minute;
        $cutoffMinutes = BookingSlotCutoff::cutoffMinutes();
        $resumeMinutes = self::SUBMISSION_CUTOFF_RESUME_HOUR * 60 + self::SUBMISSION_CUTOFF_RESUME_MINUTE;

        return $minutes >= $cutoffMinutes || $minutes < $resumeMinutes;
    }

    public static function reservationCutoffValidationMessage(): string
    {
        return 'Reservations are closed for today. You may reserve again starting 9:00 AM tomorrow.';
    }

    /**
     * True if some portion of [start, end) falls outside daily open/close windows (per Manila/app tz calendar day).
     * AVR and Lobby are exempt (multi-day / extended-hour events).
     */
    public static function reservationOutsideOperatingHours($start, $end, string $tz, ?Space $space = null): bool
    {
        if ($space !== null && $space->exemptFromOperatingHoursValidation()) {
            return false;
        }

        try {
            $start = $start instanceof Carbon ? $start : Carbon::parse((string) $start, $tz);
            $end = $end instanceof Carbon ? $end : Carbon::parse((string) $end, $tz);
        } catch (\Throwable) {
            return true;
        }

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
                $open = Carbon::parse($day->format('Y-m-d').' '.$win['start'], $tz);
                $close = Carbon::parse($day->format('Y-m-d').' '.$win['end'], $tz);
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
