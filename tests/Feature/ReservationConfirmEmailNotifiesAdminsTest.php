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

class ReservationConfirmEmailNotifiesAdminsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSpace(): Space
    {
        return Space::create([
            'name' => 'AVR',
            'slug' => 'avr',
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

    public function test_valid_confirmation_sends_mail_to_all_admins(): void
    {
        Mail::fake();

        $admin1 = $this->makeUserWithRole('admin');
        $admin2 = $this->makeUserWithRole('admin');
        $nonAdmin = $this->makeUserWithRole('student');

        $reservation = $this->makePendingVerificationReservation();

        $response = $this->postJson('/api/reservations/confirm-email', [
            'token' => $reservation->verification_token,
        ]);

        $response->assertStatus(200);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_APPROVAL, $reservation->status);
        $this->assertNotNull($reservation->verified_at);

        Mail::assertSent(ReservationPendingApprovalAdminMail::class, 2);

        $sentTo = [];
        Mail::assertSent(ReservationPendingApprovalAdminMail::class, function (ReservationPendingApprovalAdminMail $mail) use (&$sentTo) {
            $addresses = collect($mail->to)->pluck('address')->all();
            $sentTo = array_merge($sentTo, $addresses);
            return true;
        });

        $this->assertContains($admin1->email, $sentTo);
        $this->assertContains($admin2->email, $sentTo);
        $this->assertNotContains($nonAdmin->email, $sentTo);
    }

    public function test_invalid_64_character_token_sends_no_admin_mail(): void
    {
        Mail::fake();

        $this->makeUserWithRole('admin');
        $this->makePendingVerificationReservation();

        $invalidToken = str_repeat('a', 64);

        $response = $this->postJson('/api/reservations/confirm-email', [
            'token' => $invalidToken,
        ]);

        $response->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_expired_token_sends_no_admin_mail(): void
    {
        Mail::fake();

        $this->makeUserWithRole('admin');

        $reservation = $this->makePendingVerificationReservation();
        $reservation->update([
            'verification_expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/reservations/confirm-email', [
            'token' => $reservation->verification_token,
        ]);

        $response->assertStatus(422);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_REJECTED, $reservation->status);

        Mail::assertNothingSent();
    }
}

