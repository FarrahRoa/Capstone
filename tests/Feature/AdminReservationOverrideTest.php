<?php

namespace Tests\Feature;

use App\Mail\Reservation\ReservationDisplacedByAdminOverrideMail;
use App\Mail\Reservation\ReservationGloballyOverriddenMail;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\ReservationOverrideLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReservationOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Test role',
        ]);

        return User::factory()->create([
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);
    }

    private function makeSpace(string $suffix = 'a'): Space
    {
        return Space::create([
            'name' => 'Room '.strtoupper($suffix),
            'slug' => 'room-'.$suffix.'-'.uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    private function makeReservation(string $status): Reservation
    {
        $role = Role::create([
            'name' => 'Student',
            'slug' => 'student',
            'description' => 'Test role',
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'email' => 'faculty-override-'.uniqid().'@xu.edu.ph',
        ]);

        $space = $this->makeSpace();

        return Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => $status,
            'purpose' => 'Test reservation',
        ]);
    }

    private function overridePayload(Reservation $r, ?Space $target = null): array
    {
        $space = $target ?? Space::find($r->space_id);

        return [
            'reason' => 'Approved via global override',
            'space_id' => $space->id,
            'start_at' => $r->start_at->toIso8601String(),
            'end_at' => $r->end_at->toIso8601String(),
        ];
    }

    public function test_global_override_on_pending_approval_succeeds_and_sends_mail(): void
    {
        Mail::fake();

        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_PENDING_APPROVAL);
        $target = $this->makeSpace('b');

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/override", $this->overridePayload($reservation, $target));

        $response->assertStatus(200);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_OVERRIDDEN, $reservation->status);
        $this->assertSame($target->id, (int) $reservation->space_id);
        $this->assertSame('1AVR', $reservation->reservation_number);
        $this->assertSame($admin->id, (int) $reservation->overridden_by);
        $this->assertNotNull($reservation->overridden_at);
        $this->assertSame('Approved via global override', $reservation->override_reason);

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'actor_user_id' => $admin->id,
            'actor_type' => ReservationLog::ACTOR_ADMIN,
            'action' => ReservationLog::ACTION_OVERRIDE,
            'notes' => 'Approved via global override',
        ]);

        $this->assertDatabaseHas('reservation_override_logs', [
            'reservation_id' => $reservation->id,
            'admin_user_id' => $admin->id,
            'new_space_id' => $target->id,
        ]);

        Mail::assertSent(ReservationGloballyOverriddenMail::class, function (ReservationGloballyOverriddenMail $mail) use ($reservation) {
            return $mail->hasTo($reservation->user->email);
        });
    }

    public function test_global_override_on_approved_reservation_succeeds(): void
    {
        Mail::fake();
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);
        $reservation = $this->makeReservation(Reservation::STATUS_APPROVED);
        $reservation->update(['reservation_number' => 'RES-TEST01']);
        $target = $this->makeSpace('c');

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/override", $this->overridePayload($reservation, $target));
        $response->assertStatus(200);
        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_OVERRIDDEN, $reservation->status);
        $this->assertSame($target->id, (int) $reservation->space_id);
    }

    public function test_global_override_requires_reason(): void
    {
        Mail::fake();
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);
        $reservation = $this->makeReservation(Reservation::STATUS_PENDING_APPROVAL);
        $target = $this->makeSpace('d');

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/override", [
            'reason' => '  ',
            'space_id' => $target->id,
            'start_at' => $reservation->start_at->toIso8601String(),
            'end_at' => $reservation->end_at->toIso8601String(),
        ]);
        $response->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_global_override_displaces_lower_priority_conflict(): void
    {
        Mail::fake();
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $studentRole = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 't']);
        $student = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);
        $space = $this->makeSpace('x');
        $windowStart = now()->addDays(2)->setTime(14, 0);
        $windowEnd = now()->addDays(2)->setTime(15, 0);

        $blocking = Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => $windowStart,
            'end_at' => $windowEnd,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Blocking',
        ]);

        $facultyRole = Role::firstOrCreate(
            ['slug' => 'faculty'],
            ['name' => 'Faculty', 'description' => 't']
        );
        $facultyUser = User::factory()->create(['role_id' => $facultyRole->id, 'is_activated' => true, 'user_type' => User::USER_TYPE_FACULTY_STAFF]);
        $pending = Reservation::create([
            'user_id' => $facultyUser->id,
            'space_id' => $this->makeSpace('y')->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Will override into blocking slot',
        ]);

        $response = $this->postJson("/api/admin/reservations/{$pending->id}/override", [
            'reason' => 'Campus event priority',
            'space_id' => $space->id,
            'start_at' => $windowStart->toIso8601String(),
            'end_at' => $windowEnd->toIso8601String(),
        ]);
        $response->assertStatus(200);

        $blocking->refresh();
        $this->assertSame(Reservation::STATUS_RESCHEDULE_REQUIRED, $blocking->status);
        Mail::assertSent(ReservationDisplacedByAdminOverrideMail::class);
    }

    public function test_global_override_blocked_when_conflict_higher_priority(): void
    {
        Mail::fake();
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $facultyRole = Role::where('slug', 'faculty')->first() ?? Role::create(['slug' => 'faculty', 'name' => 'Faculty', 'description' => 't']);
        $facultyUser = User::factory()->create([
            'role_id' => $facultyRole->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'college_office' => 'Office of the President',
        ]);
        $studentRole = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 't']);
        $student = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);

        $space = $this->makeSpace('prio');
        $windowStart = now()->addDays(3)->setTime(11, 0);
        $windowEnd = now()->addDays(3)->setTime(12, 0);

        $blocking = Reservation::create([
            'user_id' => $facultyUser->id,
            'space_id' => $space->id,
            'start_at' => $windowStart,
            'end_at' => $windowEnd,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'OP booking',
        ]);

        $pending = Reservation::create([
            'user_id' => $student->id,
            'space_id' => $this->makeSpace('z')->id,
            'start_at' => now()->addDay()->setTime(8, 0),
            'end_at' => now()->addDay()->setTime(9, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Student tries to bump OP',
        ]);

        $response = $this->postJson("/api/admin/reservations/{$pending->id}/override", [
            'reason' => 'Should fail',
            'space_id' => $space->id,
            'start_at' => $windowStart->toIso8601String(),
            'end_at' => $windowEnd->toIso8601String(),
        ]);
        $response->assertStatus(422);

        $blocking->refresh();
        $this->assertSame(Reservation::STATUS_APPROVED, $blocking->status);
        Mail::assertNothingSent();
    }

    public function test_override_on_rejected_returns_422_and_sends_no_mail(): void
    {
        Mail::fake();

        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $reservation = $this->makeReservation(Reservation::STATUS_REJECTED);

        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/override", $this->overridePayload($reservation));

        $response->assertStatus(422);

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_REJECTED, $reservation->status);

        $this->assertDatabaseMissing('reservation_logs', [
            'reservation_id' => $reservation->id,
            'action' => ReservationLog::ACTION_OVERRIDE,
        ]);

        Mail::assertNothingSent();
    }

    public function test_librarian_cannot_access_global_override_endpoint(): void
    {
        Mail::fake();
        $libRole = Role::firstOrCreate(['slug' => 'librarian'], ['name' => 'Librarian', 'description' => 't']);
        $lib = User::factory()->create(['role_id' => $libRole->id, 'is_activated' => true]);
        $reservation = $this->makeReservation(Reservation::STATUS_PENDING_APPROVAL);
        Sanctum::actingAs($lib);
        $response = $this->postJson("/api/admin/reservations/{$reservation->id}/override", $this->overridePayload($reservation));
        $response->assertStatus(403);
        Mail::assertNothingSent();
    }
}
