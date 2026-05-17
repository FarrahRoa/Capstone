<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Library reservation approved – ' . ($this->reservation->reservation_number ?? '#'.$this->reservation->id),
            from: config('mail.from.address'),
        );
    }

    private static function extractFloorFromGuidelines(mixed $guidelineDetails): ?string
    {
        $details = is_array($guidelineDetails) ? $guidelineDetails : [];
        $raw = $details['location'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $t = trim($raw);

        // Common patterns: "2nd Floor", "3rd floor – Library", "Ground Floor (Lobby)".
        if (preg_match('/\b(ground|[0-9]+(?:st|nd|rd|th)?)\s*floor\b/i', $t, $m)) {
            $word = $m[1];
            $word = is_string($word) ? trim($word) : '';
            if ($word === '') return null;
            $normalized = ctype_digit($word) ? ($word . 'th') : $word;
            $normalized = ucfirst(strtolower($normalized));
            return $normalized . ' Floor';
        }

        // Fallback: any snippet containing "floor".
        if (preg_match('/\b[^.]{0,40}\bfloor\b[^.]{0,40}\b/i', $t, $m)) {
            $snippet = trim($m[0]);
            return $snippet !== '' ? $snippet : null;
        }

        return null;
    }

    public function content(): Content
    {
        $this->reservation->loadMissing('space', 'user');
        $space = $this->reservation->space;
        $assignedSpaceName = $space?->name ? (string) $space->name : null;
        $locationFloor = $space
            ? self::extractFloorFromGuidelines($space->guideline_details)
            : null;

        return new Content(
            view: 'emails.reservation-approved',
            with: [
                'reservation' => $this->reservation,
                'assignedSpaceName' => $assignedSpaceName,
                'locationFloor' => $locationFloor,
            ],
        );
    }
}
