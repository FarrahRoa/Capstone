<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Support\UserAffiliationChangePolicy;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAffiliationChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function studentUser(int $collegeId, ?Carbon $lastChanged = null): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test role']
        );

        return User::create([
            'name' => 'Student User',
            'email' => 'aff-stu@my.xu.edu.ph',
            'password' => Hash::make('secret'),
            'role_id' => $role->id,
            'is_activated' => true,
            'mobile_number' => '09170000000',
            'user_type' => User::USER_TYPE_STUDENT,
            'college_id' => $collegeId,
            'college_office' => College::query()->find($collegeId)?->name,
            'profile_completed_at' => now()->subMonth(),
            'last_affiliation_changed_at' => $lastChanged,
        ]);
    }

    private function employeeUser(int $officeId, ?Carbon $lastChanged = null): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'faculty'],
            ['name' => 'Faculty', 'description' => 'Test role']
        );

        return User::create([
            'name' => 'Employee User',
            'email' => 'aff-emp@my.xu.edu.ph',
            'password' => Hash::make('secret'),
            'role_id' => $role->id,
            'is_activated' => true,
            'mobile_number' => '09170000001',
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'office_id' => $officeId,
            'college_office' => Office::query()->find($officeId)?->name,
            'profile_completed_at' => now()->subMonth(),
            'last_affiliation_changed_at' => $lastChanged,
        ]);
    }

    public function test_student_first_affiliation_change_sets_timestamp(): void
    {
        $this->seed(RoleSeeder::class);

        $collegeA = College::create(['name' => 'College A']);
        $collegeB = College::create(['name' => 'College B']);
        $user = $this->studentUser($collegeA->id);

        Sanctum::actingAs($user);

        $resp = $this->patchJson('/api/me/account', [
            'name' => 'Student User',
            'mobile_number' => '09170000000',
            'college_id' => $collegeB->id,
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('data.college_id', $collegeB->id);
        $resp->assertJsonPath('data.affiliation_change_eligible', false);

        $user->refresh();
        $this->assertSame($collegeB->id, $user->college_id);
        $this->assertNotNull($user->last_affiliation_changed_at);
    }

    public function test_student_cannot_change_college_within_one_month(): void
    {
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-05-05 14:00:00'));

        $collegeA = College::create(['name' => 'College A']);
        $collegeB = College::create(['name' => 'College B']);
        $collegeC = College::create(['name' => 'College C']);
        $user = $this->studentUser($collegeA->id, Carbon::parse('2026-05-05 10:00:00'));

        Sanctum::actingAs($user);

        $resp = $this->patchJson('/api/me/account', [
            'name' => 'Student User',
            'mobile_number' => '09170000000',
            'college_id' => $collegeB->id,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('errors.college_id.0', UserAffiliationChangePolicy::BLOCKED_MESSAGE);

        $user->refresh();
        $this->assertSame($collegeA->id, $user->college_id);
    }

    public function test_student_can_change_college_after_one_calendar_month(): void
    {
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-06-05 09:00:00'));

        $collegeA = College::create(['name' => 'College A']);
        $collegeB = College::create(['name' => 'College B']);
        $user = $this->studentUser($collegeA->id, Carbon::parse('2026-05-05 23:59:00'));

        Sanctum::actingAs($user);

        $resp = $this->patchJson('/api/me/account', [
            'name' => 'Student User',
            'mobile_number' => '09170000000',
            'college_id' => $collegeB->id,
        ]);

        $resp->assertOk();
        $user->refresh();
        $this->assertSame($collegeB->id, $user->college_id);
    }

    public function test_may_31_change_does_not_allow_june_1(): void
    {
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

        $collegeA = College::create(['name' => 'College A']);
        $collegeB = College::create(['name' => 'College B']);
        $user = $this->studentUser($collegeA->id, Carbon::parse('2026-05-31 08:00:00'));

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Student User',
            'mobile_number' => '09170000000',
            'college_id' => $collegeB->id,
        ])->assertStatus(422);

        Carbon::setTestNow(Carbon::parse('2026-07-01 00:00:00'));

        $this->patchJson('/api/me/account', [
            'name' => 'Student User',
            'mobile_number' => '09170000000',
            'college_id' => $collegeB->id,
        ])->assertOk();
    }

    public function test_employee_can_change_office_once_per_month(): void
    {
        $this->seed(RoleSeeder::class);

        $officeA = Office::create(['name' => 'Office A']);
        $officeB = Office::create(['name' => 'Office B']);
        $user = $this->employeeUser($officeA->id);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Employee User',
            'mobile_number' => '09170000001',
            'office_id' => $officeB->id,
        ])->assertOk();

        $user->refresh();
        $this->assertSame($officeB->id, $user->office_id);
        $this->assertNotNull($user->last_affiliation_changed_at);
    }

    public function test_complete_profile_does_not_set_last_affiliation_changed_at(): void
    {
        $this->seed(RoleSeeder::class);

        $college = College::create(['name' => 'College A']);
        $role = Role::where('slug', 'student')->first();
        $user = User::create([
            'name' => 'New Student',
            'email' => 'new-stu@my.xu.edu.ph',
            'password' => Hash::make('secret'),
            'role_id' => $role->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/me/profile', [
            'name' => 'New Student',
            'mobile_number' => '09171112222',
            'college_office' => $college->name,
            'college_id' => $college->id,
        ])->assertOk();

        $user->refresh();
        $this->assertNull($user->last_affiliation_changed_at);
        $this->assertTrue($user->isProfileComplete());
    }

    public function test_me_payload_includes_affiliation_eligibility_fields(): void
    {
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00'));

        $college = College::create(['name' => 'College A']);
        $user = $this->studentUser($college->id, Carbon::parse('2026-05-05 12:00:00'));

        Sanctum::actingAs($user);

        $resp = $this->getJson('/api/me');
        $resp->assertOk();
        $resp->assertJsonPath('data.affiliation_change_eligible', false);
        $resp->assertJsonPath('data.affiliation_next_change_on', '2026-06-05');
    }
}
