<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LibrarianInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $roleName,
        public string $temporaryPassword,
        public string $adminSignInUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'XU Library reservation system — admin access invitation',
            from: config('mail.from.address'),
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.librarian-invite',
        );
    }
}

