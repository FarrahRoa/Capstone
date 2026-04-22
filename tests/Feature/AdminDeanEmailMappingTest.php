<?php

namespace Tests\Feature;

use App\Models\DeanEmailMapping;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDeanEmailMappingTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $slug, string $name): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => 'Test']);
    }

    public function test_admin_can_create_update_and_delete_dean_email_mapping(): void
    {
        $adminRole = $this->role('admin', 'Admin');
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_activated' => true]);
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/admin/dean-email-mappings', [
            'affiliation_type' => DeanEmailMapping::TYPE_COLLEGE,
            'affiliation_name' => 'College of Nursing',
            'approver_name' => 'Dean A',
            'approver_email' => 'dean.nursing@xu.edu.ph',
            'is_active' => true,
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');
        $this->assertNotNull($id);
        $this->assertDatabaseHas('dean_email_mappings', [
            'id' => $id,
            'affiliation_type' => 'college',
            'affiliation_name' => 'College of Nursing',
            'approver_email' => 'dean.nursing@xu.edu.ph',
            'is_active' => 1,
        ]);

        $update = $this->patchJson("/api/admin/dean-email-mappings/{$id}", [
            'approver_email' => 'new.dean@xu.edu.ph',
            'is_active' => false,
        ]);
        $update->assertOk();
        $this->assertDatabaseHas('dean_email_mappings', [
            'id' => $id,
            'approver_email' => 'new.dean@xu.edu.ph',
            'is_active' => 0,
        ]);

        $delete = $this->deleteJson("/api/admin/dean-email-mappings/{$id}");
        $delete->assertOk();
        $this->assertDatabaseMissing('dean_email_mappings', ['id' => $id]);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $adminRole = $this->role('admin', 'Admin');
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_activated' => true]);
        Sanctum::actingAs($admin);

        $resp = $this->postJson('/api/admin/dean-email-mappings', [
            'affiliation_type' => DeanEmailMapping::TYPE_COLLEGE,
            'affiliation_name' => 'College of Nursing',
            'approver_email' => 'not-an-email',
        ]);
        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['approver_email']);
    }

    public function test_non_admin_cannot_manage_dean_email_mappings(): void
    {
        $studentRole = $this->role('student', 'Student');
        $user = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);
        Sanctum::actingAs($user);

        $resp = $this->getJson('/api/admin/dean-email-mappings');
        $resp->assertStatus(403);
    }

    public function test_college_type_rejects_non_official_college_name(): void
    {
        $adminRole = $this->role('admin', 'Admin');
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_activated' => true]);
        Sanctum::actingAs($admin);

        $resp = $this->postJson('/api/admin/dean-email-mappings', [
            'affiliation_type' => DeanEmailMapping::TYPE_COLLEGE,
            'affiliation_name' => 'Fake College Of Typos',
            'approver_email' => 'dean@xu.edu.ph',
        ]);
        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['affiliation_name']);
        $this->assertDatabaseCount('dean_email_mappings', 0);
    }

    public function test_office_type_rejects_college_list_value(): void
    {
        $adminRole = $this->role('admin', 'Admin');
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_activated' => true]);
        Sanctum::actingAs($admin);

        $resp = $this->postJson('/api/admin/dean-email-mappings', [
            'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
            'affiliation_name' => 'College of Nursing',
            'approver_email' => 'dean@xu.edu.ph',
        ]);
        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['affiliation_name']);
        $this->assertDatabaseCount('dean_email_mappings', 0);
    }

    public function test_patch_can_change_affiliation_to_another_valid_office(): void
    {
        $adminRole = $this->role('admin', 'Admin');
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_activated' => true]);
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/admin/dean-email-mappings', [
            'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
            'affiliation_name' => 'Finance',
            'approver_email' => 'finance.dean@xu.edu.ph',
            'is_active' => true,
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');

        $patch = $this->patchJson("/api/admin/dean-email-mappings/{$id}", [
            'affiliation_name' => 'PPO',
        ]);
        $patch->assertOk();
        $this->assertDatabaseHas('dean_email_mappings', [
            'id' => $id,
            'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
            'affiliation_name' => 'PPO',
        ]);
    }

    public function test_dean_mapping_college_value_matches_complete_profile_allowed_list(): void
    {
        $colleges = User::allowedStudentColleges();
        $this->assertContains('College of Nursing', $colleges);

        $adminRole = $this->role('admin', 'Admin');
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_activated' => true]);
        Sanctum::actingAs($admin);

        $resp = $this->postJson('/api/admin/dean-email-mappings', [
            'affiliation_type' => DeanEmailMapping::TYPE_COLLEGE,
            'affiliation_name' => 'College of Nursing',
            'approver_email' => 'nursing.dean@xu.edu.ph',
        ]);
        $resp->assertStatus(201);
        $this->assertDatabaseHas('dean_email_mappings', [
            'affiliation_name' => 'College of Nursing',
            'affiliation_type' => DeanEmailMapping::TYPE_COLLEGE,
        ]);
    }
}

