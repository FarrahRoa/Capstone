<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ReservationDeanReviewRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $reviewUrl;

    public string $approveUrl;

    public string $rejectUrl;

    public function __construct(
        public Reservation $reservation
    ) {
        $expiry = now()->addDays(21);
        $id = $reservation->id;
        $this->reviewUrl = URL::temporarySignedRoute('dean.reservations.review', $expiry, ['reservation' => $id]);
        $this->approveUrl = URL::temporarySignedRoute('dean.reservations.approve', $expiry, ['reservation' => $id]);
        $this->rejectUrl = URL::temporarySignedRoute('dean.reservations.reject', $expiry, ['reservation' => $id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action required: AVR/Lobby reservation — dean/office approval',
            from: config('mail.from.address'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dean-reservation-review-request',
        );
    }
}
