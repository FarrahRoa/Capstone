<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserEligibilityManagementTest extends TestCase
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

    public function test_admin_can_list_users(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);

        $target = User::factory()->create([
            'name' => 'Medical User',
            'email' => 'medical@example.com',
            'med_confab_eligible' => true,
            'boardroom_eligible' => false,
        ]);

        $response = $this->getJson('/api/admin/users?search=medical');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'per_page',
            'total',
        ]);
        $row = collect($response->json('data'))->firstWhere('email', 'medical@example.com');
        $this->assertNotNull($row);
        $this->assertSame('Medical User', $row['name']);
        $this->assertTrue($row['med_confab_eligible']);
        $this->assertFalse($row['boardroom_eligible']);
    }

    public function test_admin_users_list_is_paginated(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);

        User::factory()->count(12)->create();

        $response = $this->getJson('/api/admin/users?per_page=5&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('per_page', 5);
        $response->assertJsonPath('current_page', 2);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_admin_users_invalid_per_page_is_rejected(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?per_page=200');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');
    }

    public function test_admin_can_list_roles_for_management_dropdown(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        $this->makeUserWithRole('student_assistant', 'Student Assistant');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200);
        $response->assertJsonFragment(['slug' => 'admin']);
        $response->assertJsonFragment(['slug' => 'student_assistant']);
    }

    public function test_admin_can_update_eligibility_flags(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);

        $target = User::factory()->create([
            'med_confab_eligible' => false,
            'boardroom_eligible' => false,
        ]);

        $response = $this->patchJson("/api/admin/users/{$target->id}", [
            'med_confab_eligible' => true,
            'boardroom_eligible' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'med_confab_eligible' => true,
            'boardroom_eligible' => true,
        ]);
    }

    public function test_admin_can_update_another_users_role(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);
        $staffRole = Role::firstOrCreate(['slug' => 'staff'], ['name' => 'Staff', 'description' => 'Test role']);
        $target = $this->makeUserWithRole('student', 'Student');

        $response = $this->patchJson("/api/admin/users/{$target->id}", [
            'role_id' => $staffRole->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role_id' => $staffRole->id,
        ]);
    }

    public function test_invalid_role_assignment_is_rejected(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);
        $target = $this->makeUserWithRole('student', 'Student');

        $response = $this->patchJson("/api/admin/users/{$target->id}", [
            'role_id' => 999999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('role_id');
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);
        $studentRole = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test role']);

        $response = $this->patchJson("/api/admin/users/{$admin->id}", [
            'role_id' => $studentRole->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Cannot remove the last remaining admin.',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role_id' => $admin->role_id,
        ]);
    }

    public function test_admin_can_demote_another_admin_when_more_than_one_admin_exists(): void
    {
        $actingAdmin = $this->makeUserWithRole('admin', 'Admin');
        $otherAdmin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($actingAdmin);

        $studentRole = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test role']);
        $response = $this->patchJson("/api/admin/users/{$otherAdmin->id}", [
            'role_id' => $studentRole->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'role_id' => $studentRole->id,
        ]);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_non_admin_cannot_update_eligibility_flags(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);

        $target = User::factory()->create([
            'med_confab_eligible' => false,
            'boardroom_eligible' => false,
        ]);

        $response = $this->patchJson("/api/admin/users/{$target->id}", [
            'med_confab_eligible' => true,
            'boardroom_eligible' => true,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'med_confab_eligible' => false,
            'boardroom_eligible' => false,
        ]);
    }

    public function test_non_admin_cannot_update_roles(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);

        $target = $this->makeUserWithRole('faculty', 'Faculty');
        $staffRole = Role::firstOrCreate(['slug' => 'staff'], ['name' => 'Staff', 'description' => 'Test role']);

        $response = $this->patchJson("/api/admin/users/{$target->id}", [
            'role_id' => $staffRole->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role_id' => $target->role_id,
        ]);
    }
}
