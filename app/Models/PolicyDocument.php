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
     * @return array{day_start: string, day_end: string}
     */
    public static function defaultOperatingHours(): array
    {
        return [
            'day_start' => '09:00',
            'day_end' => '18:00',
        ];
    }
}
