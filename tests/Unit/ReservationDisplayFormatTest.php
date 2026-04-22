<?php

namespace Tests\Unit;

use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Support\ReservationDisplayFormat;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationDisplayFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_uses_abbreviated_month_zero_padded_day(): void
    {
        $dt = Carbon::parse('2026-04-06 00:00:00', 'Asia/Manila');
        $this->assertSame('Apr 06 2026', ReservationDisplayFormat::date($dt));
    }

    public function test_time_uses_twelve_hour_without_hour_17_style(): void
    {
        $dt = Carbon::parse('2026-04-06 17:00:00', 'Asia/Manila');
        $this->assertSame('5:00 PM', ReservationDisplayFormat::time($dt));
        $combined = ReservationDisplayFormat::date($dt).' · '.ReservationDisplayFormat::time($dt);
        $this->assertStringNotContainsString('17:00', $combined);
        $this->assertStringNotContainsString('17:00 PM', $combined);
    }

    public function test_date_and_times_single_day(): void
    {
        $s = Carbon::parse('2026-04-16 09:30:00', 'Asia/Manila');
        $e = Carbon::parse('2026-04-16 17:30:00', 'Asia/Manila');
        $this->assertSame('Apr 16 2026 · 9:30 AM – 5:30 PM', ReservationDisplayFormat::dateAndTimes($s, $e));
    }

    public function test_verification_email_view_contains_formatted_schedule_not_slash_date(): void
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 't']);
        $user = User::factory()->create(['role_id' => $role->id, 'is_activated' => true]);
        $space = Space::create([
            'name' => 'AVR',
            'slug' => 'avr-mail-'.uniqid(),
            'type' => 'avr',
            'capacity' => 8,
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => Carbon::parse('2026-06-15 09:00:00', 'Asia/Manila'),
            'end_at' => Carbon::parse('2026-06-15 10:00:00', 'Asia/Manila'),
            'status' => Reservation::STATUS_EMAIL_VERIFICATION_PENDING,
            'purpose' => 'Test',
        ]);
        $reservation->load('space');

        $html = view('emails.reservation-verify', ['reservation' => $reservation])->render();

        $this->assertStringContainsString('Jun 15 2026', $html);
        $this->assertStringContainsString('9:00 AM', $html);
        $this->assertStringContainsString('10:00 AM', $html);
        $this->assertStringNotContainsString('06/15/2026', $html);
        $this->assertStringNotContainsString('17:00 PM', $html);
    }
}
