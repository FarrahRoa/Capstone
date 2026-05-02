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

    /** Signed GET: full review (both actions). */
    public string $reviewUrl;

    /** Signed GET: open review page pre-focused on approve (confirm via POST in browser). */
    public string $approveReviewUrl;

    /** Signed GET: open review page pre-focused on reject (confirm via POST in browser). */
    public string $rejectReviewUrl;

    public function __construct(
        public Reservation $reservation
    ) {
        $expiry = now()->addDays(21);
        $id = $reservation->id;
        $this->reviewUrl = URL::temporarySignedRoute('dean.reservations.review', $expiry, ['reservation' => $id]);
        $this->approveReviewUrl = URL::temporarySignedRoute('dean.reservations.review', $expiry, [
            'reservation' => $id,
            'intent' => 'approve',
        ]);
        $this->rejectReviewUrl = URL::temporarySignedRoute('dean.reservations.review', $expiry, [
            'reservation' => $id,
            'intent' => 'reject',
        ]);
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
