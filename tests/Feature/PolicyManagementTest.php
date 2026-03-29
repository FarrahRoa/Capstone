<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PolicyManagementTest extends TestCase
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

    public function test_authenticated_user_can_view_reservation_guidelines(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/reservation-guidelines');

        $response->assertStatus(200);
        $response->assertJsonStructure(['slug', 'content', 'updated_at']);
        $response->assertJsonPath('slug', PolicyDocument::SLUG_RESERVATION_GUIDELINES);
        $this->assertNotSame('', $response->json('content'));
        $this->assertDatabaseHas('policy_documents', [
            'slug' => PolicyDocument::SLUG_RESERVATION_GUIDELINES,
        ]);
    }

    public function test_admin_with_policies_manage_can_view_and_update_guidelines(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);

        $getResponse = $this->getJson('/api/admin/policies/reservation-guidelines');
        $getResponse->assertStatus(200);
        $getResponse->assertJsonPath('slug', PolicyDocument::SLUG_RESERVATION_GUIDELINES);

        $newContent = "Line one.\n\nLine two for thesis demo.";

        $putResponse = $this->putJson('/api/admin/policies/reservation-guidelines', [
            'content' => $newContent,
        ]);

        $putResponse->assertStatus(200);
        $putResponse->assertJsonFragment([
            'message' => 'Reservation guidelines saved.',
            'content' => $newContent,
        ]);

        $this->assertDatabaseHas('policy_documents', [
            'slug' => PolicyDocument::SLUG_RESERVATION_GUIDELINES,
            'content' => $newContent,
        ]);
    }

    public function test_non_admin_cannot_update_guidelines(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);

        $doc = PolicyDocument::reservationGuidelines();
        $original = $doc->content;

        $response = $this->putJson('/api/admin/policies/reservation-guidelines', [
            'content' => 'Should not save',
        ]);

        $response->assertStatus(403);
        $this->assertSame($original, $doc->fresh()->content);
    }

    public function test_non_admin_cannot_access_admin_policy_read_endpoint(): void
    {
        $student = $this->makeUserWithRole('student', 'Student');
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/admin/policies/reservation-guidelines');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_view_guidelines_endpoint(): void
    {
        $response = $this->getJson('/api/reservation-guidelines');

        $response->assertStatus(401);
    }

    public function test_update_validation_requires_content(): void
    {
        $admin = $this->makeUserWithRole('admin', 'Admin');
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/policies/reservation-guidelines', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('content');
    }
}
