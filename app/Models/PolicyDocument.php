<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyDocument extends Model
{
    public const SLUG_RESERVATION_GUIDELINES = 'reservation_guidelines';

    protected $fillable = ['slug', 'content'];

    public static function reservationGuidelines(): self
    {
        return self::firstOrCreate(
            ['slug' => self::SLUG_RESERVATION_GUIDELINES],
            ['content' => self::defaultReservationGuidelines()]
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
}
