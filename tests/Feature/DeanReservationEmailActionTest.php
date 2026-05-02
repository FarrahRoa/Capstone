<?php

namespace Tests\Feature;

use App\Mail\Reservation\ReservationPendingApprovalAdminMail;
use App\Mail\Reservation\ReservationUserDeanApprovedMail;
use App\Mail\Reservation\ReservationUserDeanRejectedMail;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeanReservationEmailActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSacdevDeanMappingForTests();
    }

    private function makeAdminUser(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Test role']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function makePendingDeanReservation(): Reservation
    {
        $user = User::factory()->create([
            'role_id' => Role::firstOrCreate(
                ['slug' => 'student'],
                ['name' => 'Student', 'description' => 'Test role']
            )->id,
            'is_activated' => true,
        ]);
        $space = Space::create([
            'name' => 'AVR',
            'slug' => 'avr-'.Str::lower(Str::random(6)),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        return Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_DEAN_APPROVAL,
            'purpose' => 'Dean action test',
            'verified_at' => now(),
            'event_request_type' => Reservation::EVENT_REQUEST_ORGANIZATION,
        ]);
    }

    public function test_review_get_does_not_change_status(): void
    {
        $reservation = $this->makePendingDeanReservation();
        $url = URL::temporarySignedRoute('dean.reservations.review', now()->addHour(), ['reservation' => $reservation->id]);

        $response = $this->get($url);
        $response->assertStatus(200);
        $response->assertSee('Review reservation', false);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_DEAN_APPROVAL, $reservation->status);
    }

    public function test_review_get_with_intent_does_not_change_status_until_post(): void
    {
        $reservation = $this->makePendingDeanReservation();
        $url = URL::temporarySignedRoute('dean.reservations.review', now()->addHour(), [
            'reservation' => $reservation->id,
            'intent' => 'approve',
        ]);

        $response = $this->get($url);
        $response->assertStatus(200);
        $response->assertSee('Confirm approval', false);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_DEAN_APPROVAL, $reservation->status);
    }

    public function test_email_style_flow_get_intent_then_post_approve(): void
    {
        Mail::fake();
        $this->makeAdminUser();
        $reservation = $this->makePendingDeanReservation();

        $reviewUrl = URL::temporarySignedRoute('dean.reservations.review', now()->addHour(), [
            'reservation' => $reservation->id,
            'intent' => 'approve',
        ]);
        $this->get($reviewUrl)->assertStatus(200);

        $postUrl = URL::temporarySignedRoute('dean.reservations.approve', now()->addHour(), ['reservation' => $reservation->id]);
        $this->post($postUrl)->assertStatus(200);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_APPROVAL, $reservation->status);
        Mail::assertSent(ReservationUserDeanApprovedMail::class, 1);
    }

    public function test_approve_post_is_idempotent(): void
    {
        Mail::fake();
        $this->makeAdminUser();
        $reservation = $this->makePendingDeanReservation();

        $url = URL::temporarySignedRoute('dean.reservations.approve', now()->addHour(), ['reservation' => $reservation->id]);
        $this->post($url)->assertStatus(200);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_PENDING_APPROVAL, $reservation->status);
        Mail::assertSent(ReservationUserDeanApprovedMail::class, 1);
        Mail::assertSent(ReservationPendingApprovalAdminMail::class, 1);

        Mail::fake();
        $this->post($url)->assertStatus(200);
        Mail::assertNothingSent();
    }

    public function test_reject_post_is_idempotent(): void
    {
        Mail::fake();
        $reservation = $this->makePendingDeanReservation();

        $url = URL::temporarySignedRoute('dean.reservations.reject', now()->addHour(), ['reservation' => $reservation->id]);
        $this->post($url)->assertStatus(200);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_REJECTED, $reservation->status);
        Mail::assertSent(ReservationUserDeanRejectedMail::class, 1);

        Mail::fake();
        $this->post($url)->assertStatus(200);
        Mail::assertNothingSent();
    }

    public function test_unsigned_post_is_forbidden(): void
    {
        $reservation = $this->makePendingDeanReservation();
        $this->post("/dean/reservations/{$reservation->id}/approve")->assertStatus(403);
    }
}
