<?php

namespace App\Mail\Reservation;

use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationDisplacedByAdminOverrideMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $displacedReservation,
        public Reservation $winningReservation,
        public string $overrideReason,
        public User $admin,
        public Space $newWinningSpace,
        public Carbon $newWinningStart,
        public Carbon $newWinningEnd,
    ) {
        $this->displacedReservation->loadMissing('space', 'user');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action required: your reservation needs a new time or room',
            from: config('mail.from.address'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-displaced-by-override',
            with: [
                'displaced' => $this->displacedReservation,
                'winning' => $this->winningReservation,
                'overrideReason' => $this->overrideReason,
                'admin' => $this->admin,
                'newSpace' => $this->newWinningSpace,
                'newStart' => $this->newWinningStart,
                'newEnd' => $this->newWinningEnd,
                'adminContact' => config('mail.from.address'),
            ],
        );
    }
}
