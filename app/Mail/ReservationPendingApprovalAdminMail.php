<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationPendingApprovalAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New reservation pending approval',
            from: config('mail.from.address'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-reservation-pending-approval',
        );
    }
}

