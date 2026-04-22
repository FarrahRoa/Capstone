<?php

namespace Tests\Feature;

use App\Mail\ReservationPendingApprovalAdminMail;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationConfirmEmailWebPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeSpace(): Space
    {
        return Space::create([
            'name' => 'Room A',
            'slug' => 'room-a',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    private function makeUserWithRole(string $slug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'description' => 'Test role']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function makePendingVerificationReservation(): Reservation
    {
        $user = $this->makeUserWithRole('student');
        $space = $this->makeSpace();

        return Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_EMAIL_VERIFICATION_PENDING,
            'purpose' => 'Test reservation',
            'verification_token' => Str::random(64),
            'verification_expires_at' => now()->addHour(),
        ]);
    }

    public function test_valid_confirmation_renders_lightweight_page_and_notifies_admins(): void
    {
        Mail::fake();

        $this->makeUserWithRole('admin');
        $reservation = $this->makePendingVerificationReservation();

        $response = $this->get('/confirm-reservation?token=' . $reservation->verification_token);

        $response->assertStatus(200);
        $response->assertHeader('Cache-Control');
        $response->assertSee('Email confirmation');
        $response->assertSee('Reservation confirmed');

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_APPROVAL, $reservation->status);
        $this->assertNotNull($reservation->verified_at);

        Mail::assertSent(ReservationPendingApprovalAdminMail::class, 1);
    }

    public function test_invalid_token_renders_error_page_and_sends_no_mail(): void
    {
        Mail::fake();
        $this->makeUserWithRole('admin');
        $this->makePendingVerificationReservation();

        $response = $this->get('/confirm-reservation?token=' . str_repeat('a', 64));

        $response->assertStatus(200);
        $response->assertSee('Invalid or expired confirmation link');
        Mail::assertNothingSent();
    }

    public function test_expired_token_renders_expired_message_and_sends_no_mail(): void
    {
        Mail::fake();
        $this->makeUserWithRole('admin');

        $reservation = $this->makePendingVerificationReservation();
        $reservation->update(['verification_expires_at' => now()->subMinute()]);

        $response = $this->get('/confirm-reservation?token=' . $reservation->verification_token);

        $response->assertStatus(200);
        $response->assertSee('Confirmation link has expired');

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_REJECTED, $reservation->status);
        Mail::assertNothingSent();
    }
}

