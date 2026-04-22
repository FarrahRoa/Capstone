<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportRequesterAffiliationTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $slug, string $name = 'Role'): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => 'Test']
        );
    }

    public function test_reports_aggregate_student_college_and_faculty_office_from_saved_profile_fields(): void
    {
        $adminRole = $this->role('admin', 'Admin');
        $studentRole = $this->role('student', 'Student');
        $facultyRole = $this->role('faculty', 'Faculty');

        $student = User::create([
            'name' => 'Student User',
            'email' => 'reportstudent@my.xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_STUDENT,
            'college_office' => 'College of Computer Studies',
            'profile_completed_at' => now(),
        ]);

        $faculty = User::create([
            'name' => 'Faculty User',
            'email' => 'reportfaculty@xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $facultyRole->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'college_office' => "Treasurer's Office",
            'profile_completed_at' => now(),
        ]);

        $space = Space::create([
            'name' => 'Report AVR',
            'slug' => 'report-avr-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $start = Carbon::now()->setTime(10, 0, 0);
        $end = Carbon::now()->setTime(11, 0, 0);

        $resStudent = Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => $start->copy(),
            'end_at' => $end->copy(),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Student report row',
            'approved_at' => now(),
        ]);

        $resFaculty = Reservation::create([
            'user_id' => $faculty->id,
            'space_id' => $space->id,
            'start_at' => $start->copy()->addHours(2),
            'end_at' => $end->copy()->addHours(2),
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'Faculty report row',
            'approved_at' => now(),
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'reportadmin@xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'college_office' => 'Finance',
            'profile_completed_at' => now(),
        ]);

        ReservationLog::create([
            'reservation_id' => $resStudent->id,
            'actor_user_id' => $admin->id,
            'actor_type' => ReservationLog::ACTOR_ADMIN,
            'action' => ReservationLog::ACTION_APPROVE,
            'notes' => 'ok',
        ]);

        $from = $start->copy()->subDay()->toDateString();
        $to = $end->copy()->addDay()->toDateString();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/reports?period=custom&from={$from}&to={$to}");
        $response->assertOk();

        $studentCollege = $response->json('data.student_college');
        $this->assertIsArray($studentCollege);
        $this->assertArrayHasKey('College of Computer Studies', $studentCollege);
        $this->assertSame(1, $studentCollege['College of Computer Studies']);

        $facultyOffices = $response->json('data.faculty_staff_office');
        $this->assertIsArray($facultyOffices);
        $this->assertArrayHasKey("Treasurer's Office", $facultyOffices);
        $this->assertSame(1, $facultyOffices["Treasurer's Office"]);

        $byUnit = $response->json('data.reservations_by_college_office');
        $this->assertIsArray($byUnit);
        $this->assertArrayHasKey('College of Computer Studies', $byUnit);
        $this->assertArrayHasKey("Treasurer's Office", $byUnit);

        $recent = $response->json('data.recent_activity');
        $this->assertIsArray($recent);
        $this->assertNotEmpty($recent);
        $first = $recent[0];
        $this->assertArrayHasKey('requester_affiliation', $first);
        $this->assertSame('College of Computer Studies', $first['requester_affiliation']);
    }

    public function test_reports_use_not_specified_only_when_college_office_is_empty(): void
    {
        $adminRole = $this->role('admin', 'Admin');
        $studentRole = $this->role('student', 'Student');

        $bare = User::create([
            'name' => 'Bare Student',
            'email' => 'barestudent@my.xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_STUDENT,
            'college_office' => null,
        ]);

        $space = Space::create([
            'name' => 'Bare AVR',
            'slug' => 'bare-avr-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $start = Carbon::now()->setTime(14, 0, 0);
        $end = Carbon::now()->setTime(15, 0, 0);

        Reservation::create([
            'user_id' => $bare->id,
            'space_id' => $space->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'No unit',
            'approved_at' => now(),
        ]);

        $admin = User::create([
            'name' => 'Admin Two',
            'email' => 'reportadmin2@xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'college_office' => 'Finance',
            'profile_completed_at' => now(),
        ]);

        $from = $start->copy()->subDay()->toDateString();
        $to = $end->copy()->addDay()->toDateString();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/reports?period=custom&from={$from}&to={$to}");
        $response->assertOk();

        $studentCollege = $response->json('data.student_college');
        $this->assertArrayHasKey('Not specified', $studentCollege);
        $this->assertSame(1, $studentCollege['Not specified']);
    }

    public function test_export_json_includes_requester_affiliation_on_reservation_rows(): void
    {
        $adminRole = $this->role('admin', 'Admin');
        $studentRole = $this->role('student', 'Student');

        $student = User::create([
            'name' => 'Export Student',
            'email' => 'exportstu@my.xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_STUDENT,
            'college_office' => 'College of Nursing',
            'profile_completed_at' => now(),
        ]);

        $space = Space::create([
            'name' => 'Ex AVR',
            'slug' => 'ex-avr-' . uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ]);

        $start = Carbon::now()->setTime(8, 0, 0);
        $end = Carbon::now()->setTime(9, 0, 0);

        Reservation::create([
            'user_id' => $student->id,
            'space_id' => $space->id,
            'start_at' => $start,
            'end_at' => $end,
            'status' => Reservation::STATUS_APPROVED,
            'purpose' => 'export',
            'approved_at' => now(),
        ]);

        $admin = User::create([
            'name' => 'Admin Ex',
            'email' => 'reportadminex@xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'college_office' => 'Finance',
            'profile_completed_at' => now(),
        ]);

        $from = $start->copy()->subDay()->toDateString();
        $to = $end->copy()->addDay()->toDateString();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/reports?period=custom&from={$from}&to={$to}");
        $response->assertOk();

        $rows = $response->json('data.reservation_rows');
        $this->assertIsArray($rows);
        $match = collect($rows)->first(fn ($r) => ($r['requester_email'] ?? '') === 'exportstu@my.xu.edu.ph');
        $this->assertNotNull($match);
        $this->assertSame('College of Nursing', $match['requester_affiliation']);
    }
}
