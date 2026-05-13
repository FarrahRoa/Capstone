<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationGloballyOverriddenMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public User $admin,
        public Carbon $previousStart,
        public Carbon $previousEnd,
    ) {
        $this->reservation->loadMissing('space', 'user');
    }

    public function envelope(): Envelope
    {
        $num = $this->reservation->reservation_number ?? (string) $this->reservation->id;

        return new Envelope(
            subject: 'Library reservation updated by administrator – ' . $num,
            from: config('mail.from.address'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-globally-overridden',
            with: [
                'reservation' => $this->reservation,
                'admin' => $this->admin,
                'previousStart' => $this->previousStart,
                'previousEnd' => $this->previousEnd,
                'adminContact' => config('mail.from.address'),
            ],
        );
    }
}
