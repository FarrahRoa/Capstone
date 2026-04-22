<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardFeedTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $slug, string $name): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => 'Test role']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function makeSpace(): Space
    {
        $slug = 'dash-' . Str::lower(Str::random(8));

        return Space::create([
            'name' => 'Dashboard Test Space',
            'slug' => $slug,
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);
    }

    public function test_recent_reservation_logs_endpoint_returns_audit_rows(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        $student = $this->makeUserWithRole('student', 'Student');
        $space = $this->makeSpace();
        $reservation = Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Feed test',
        ]);
        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'actor_user_id' => $student->id,
            'actor_type' => ReservationLog::ACTOR_USER,
            'action' => ReservationLog::ACTION_CREATE,
            'notes' => null,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/activity/reservation-logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'action',
                    'action_label',
                    'actor_type',
                    'requester',
                    'space',
                    'slot',
                ],
            ],
        ]);
        $this->assertSame('Created', $response->json('data.0.action_label'));
        $this->assertSame('Dashboard Test Space', $response->json('data.0.space.name'));
    }

    public function test_users_index_supports_sort_recent(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        $studentRole = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test role']
        );
        $older = User::factory()->create([
            'role_id' => $studentRole->id,
            'email' => 'older-feed-test@my.xu.edu.ph',
            'is_activated' => true,
        ]);
        $newer = User::factory()->create([
            'role_id' => $studentRole->id,
            'email' => 'newer-feed-test@my.xu.edu.ph',
            'is_activated' => true,
        ]);
        DB::table('users')->where('id', $older->id)->update(['created_at' => now()->subDays(3)]);
        DB::table('users')->where('id', $newer->id)->update(['created_at' => now()]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/users?sort=recent&per_page=10');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame($newer->id, $ids[0]);
        $this->assertContains($older->id, $ids);
    }

    public function test_student_cannot_fetch_reservation_logs_feed(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);
        $this->getJson('/api/admin/activity/reservation-logs')->assertStatus(403);
    }
}
