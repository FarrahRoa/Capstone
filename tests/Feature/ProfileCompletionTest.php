<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_email_domain_is_classified_correctly(): void
    {
        $this->assertSame(User::USER_TYPE_STUDENT, User::getUserTypeFromEmail('a@my.xu.edu.ph'));
    }

    public function test_faculty_staff_email_domain_is_classified_correctly(): void
    {
        $this->assertSame(User::USER_TYPE_FACULTY_STAFF, User::getUserTypeFromEmail('a@xu.edu.ph'));
    }

    public function test_me_flags_profile_completion_required_when_activated_user_missing_unit(): void
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);
        $user = User::factory()->create([
            'email' => 'incomplete@my.xu.edu.ph',
            'name' => 'XU User',
            'role_id' => $role->id,
            'is_activated' => true,
            'college_office' => null,
            'user_type' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');
        $response->assertOk();
        $response->assertJsonPath('data.user_type', User::USER_TYPE_STUDENT);
        $response->assertJsonPath('data.profile_complete', false);
        $response->assertJsonPath('data.requires_profile_completion', true);
    }

    public function test_student_can_complete_profile_only_with_allowed_college_values(): void
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);
        $user = User::factory()->create([
            'email' => 'student1@my.xu.edu.ph',
            'name' => 'XU User',
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
        Sanctum::actingAs($user);

        $ok = $this->postJson('/api/me/profile', [
            'name' => 'Juan Dela Cruz',
            'college_office' => 'College of Computer Studies',
            'mobile_number' => '09171234567',
        ]);
        $ok->assertOk();
        $ok->assertJsonPath('data.name', 'Juan Dela Cruz');
        $ok->assertJsonPath('data.college_office', 'College of Computer Studies');
        $ok->assertJsonPath('data.user_type', User::USER_TYPE_STUDENT);
        $ok->assertJsonPath('data.profile_complete', true);
        $ok->assertJsonPath('data.requires_profile_completion', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Juan Dela Cruz',
            'college_office' => 'College of Computer Studies',
            'mobile_number' => '09171234567',
            'user_type' => User::USER_TYPE_STUDENT,
        ]);
    }

    public function test_faculty_staff_can_complete_profile_only_with_allowed_office_values(): void
    {
        $role = Role::firstOrCreate(['slug' => 'faculty'], ['name' => 'Faculty', 'description' => 'Test']);
        $user = User::factory()->create([
            'email' => 'faculty1@xu.edu.ph',
            'name' => 'XU User',
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
        Sanctum::actingAs($user);

        $ok = $this->postJson('/api/me/profile', [
            'name' => 'Maria Santos',
            'college_office' => "Treasurer's Office",
            'mobile_number' => '09981234567',
        ]);
        $ok->assertOk();
        $ok->assertJsonPath('data.name', 'Maria Santos');
        $ok->assertJsonPath('data.college_office', "Treasurer's Office");
        $ok->assertJsonPath('data.user_type', User::USER_TYPE_FACULTY_STAFF);
        $ok->assertJsonPath('data.mobile_number', '09981234567');
    }

    public function test_invalid_cross_category_selection_is_rejected(): void
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);
        $user = User::factory()->create([
            'email' => 'student2@my.xu.edu.ph',
            'name' => 'XU User',
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
        Sanctum::actingAs($user);

        $bad = $this->postJson('/api/me/profile', [
            'name' => 'Student Name',
            'college_office' => "Treasurer's Office",
            'mobile_number' => '09170000000',
        ]);
        $bad->assertStatus(422);
        $bad->assertJsonValidationErrors('college_office');

        $user->refresh();
        $this->assertNull($user->college_office);
        $this->assertNull($user->profile_completed_at);
    }

    public function test_name_is_required(): void
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);
        $user = User::factory()->create([
            'email' => 'student3@my.xu.edu.ph',
            'name' => 'XU User',
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
        Sanctum::actingAs($user);

        $bad = $this->postJson('/api/me/profile', [
            'name' => '',
            'college_office' => 'College of Computer Studies',
            'mobile_number' => '09170000000',
        ]);
        $bad->assertStatus(422);
        $bad->assertJsonValidationErrors('name');
    }

    public function test_mobile_number_is_required(): void
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);
        $user = User::factory()->create([
            'email' => 'student-mobile@my.xu.edu.ph',
            'name' => 'XU User',
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
        Sanctum::actingAs($user);

        $bad = $this->postJson('/api/me/profile', [
            'name' => 'Student Mobile',
            'college_office' => 'College of Computer Studies',
            'mobile_number' => '',
        ]);
        $bad->assertStatus(422);
        $bad->assertJsonValidationErrors('mobile_number');
    }

    public function test_completed_profile_is_reflected_in_me(): void
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);
        $user = User::factory()->create([
            'email' => 'student4@my.xu.edu.ph',
            'name' => 'Student Four',
            'college_office' => 'College of Nursing',
            'role_id' => $role->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_STUDENT,
            'profile_completed_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $me = $this->getJson('/api/me');
        $me->assertOk();
        $me->assertJsonPath('data.profile_complete', true);
        $me->assertJsonPath('data.requires_profile_completion', false);
        $me->assertJsonPath('data.name', 'Student Four');
    }

    public function test_existing_returning_users_with_completed_profile_are_not_blocked_again(): void
    {
        $role = Role::firstOrCreate(['slug' => 'faculty'], ['name' => 'Faculty', 'description' => 'Test']);
        $user = User::factory()->create([
            'email' => 'returning@xu.edu.ph',
            'name' => 'Returning Faculty',
            'college_office' => 'Research Ethics Office',
            'role_id' => $role->id,
            'is_activated' => true,
            // profile_completed_at intentionally null to simulate legacy rows
        ]);
        Sanctum::actingAs($user);

        $me = $this->getJson('/api/me');
        $me->assertOk();
        $me->assertJsonPath('data.profile_complete', true);
        $me->assertJsonPath('data.requires_profile_completion', false);
        $me->assertJsonPath('data.user_type', User::USER_TYPE_FACULTY_STAFF);
    }
}

