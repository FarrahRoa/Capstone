<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSpaceManagementTest extends TestCase
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

    private function makeNonAdminUser(): User
    {
        $role = Role::create([
            'name' => 'Student',
            'slug' => 'student',
            'description' => 'Test role',
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function makeSpace(array $overrides = []): Space
    {
        return Space::create(array_merge([
            'name' => 'Room A',
            'slug' => 'room-a',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ], $overrides));
    }

    public function test_admin_can_list_spaces(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $this->makeSpace(['name' => 'Boardroom', 'slug' => 'boardroom', 'type' => 'boardroom']);
        $this->makeSpace(['name' => 'Lobby', 'slug' => 'lobby', 'type' => 'lobby']);

        $response = $this->getJson('/api/admin/spaces');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Boardroom', $names);
        $this->assertContains('Lobby', $names);
    }

    public function test_admin_can_filter_spaces_by_search_and_type(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $this->makeSpace(['name' => 'Alpha Room', 'slug' => 'alpha-room', 'type' => 'avr']);
        $this->makeSpace(['name' => 'Beta Board', 'slug' => 'beta-board', 'type' => 'boardroom']);

        $searchResponse = $this->getJson('/api/admin/spaces?search=Beta');
        $searchResponse->assertStatus(200);
        $searchResponse->assertJsonCount(1, 'data');
        $this->assertSame('Beta Board', $searchResponse->json('data.0.name'));

        $typeResponse = $this->getJson('/api/admin/spaces?type=boardroom');
        $typeResponse->assertStatus(200);
        $typeResponse->assertJsonCount(1, 'data');
        $this->assertSame('boardroom', $typeResponse->json('data.0.type'));
    }

    public function test_invalid_type_filter_is_rejected(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/spaces?type=not-a-type');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
    }

    public function test_librarian_with_spaces_manage_can_list_spaces(): void
    {
        $librarianRole = Role::create([
            'name' => 'Librarian',
            'slug' => 'librarian',
            'description' => 'Test role',
        ]);
        $librarian = User::factory()->create([
            'role_id' => $librarianRole->id,
            'is_activated' => true,
        ]);
        Sanctum::actingAs($librarian);

        $this->makeSpace(['name' => 'Listed', 'slug' => 'listed', 'type' => 'lobby']);

        $response = $this->getJson('/api/admin/spaces');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Listed']);
    }

    public function test_admin_can_create_a_valid_space(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'New Room',
            'slug' => 'new-room',
            'type' => 'avr',
            'capacity' => 20,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/spaces', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('spaces', [
            'name' => 'New Room',
            'slug' => 'new-room',
            'type' => 'avr',
            'capacity' => 20,
            'is_active' => true,
        ]);
    }

    public function test_create_fails_with_duplicate_slug(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $this->makeSpace(['slug' => 'dup-slug']);

        $payload = [
            'name' => 'Another Room',
            'slug' => 'dup-slug',
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/spaces', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('slug');
    }

    public function test_admin_can_update_a_space(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $space = $this->makeSpace(['name' => 'Old Name', 'slug' => 'old-slug']);

        $payload = [
            'name' => 'Updated Name',
            'type' => 'boardroom',
            'capacity' => 30,
            'is_active' => false,
        ];

        $response = $this->putJson("/api/admin/spaces/{$space->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('spaces', [
            'id' => $space->id,
            'name' => 'Updated Name',
            'type' => 'boardroom',
            'capacity' => 30,
            'is_active' => false,
        ]);
    }

    public function test_update_fails_with_invalid_type(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $space = $this->makeSpace();

        $response = $this->putJson("/api/admin/spaces/{$space->id}", [
            'type' => 'invalid-type',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
    }

    public function test_admin_can_set_is_active_false_via_toggle(): void
    {
        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $space = $this->makeSpace(['is_active' => true]);

        $response = $this->postJson("/api/admin/spaces/{$space->id}/toggle-active", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $this->assertFalse($space->fresh()->is_active);
    }

    public function test_non_admin_cannot_access_admin_space_routes(): void
    {
        $user = $this->makeNonAdminUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/spaces');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_admin_space_routes(): void
    {
        $response = $this->getJson('/api/admin/spaces');

        $response->assertStatus(401);
    }

    public function test_admin_can_create_space_with_image(): void
    {
        Storage::fake('public');

        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->image('space-photo.jpg', 640, 480);

        $response = $this->post('/api/admin/spaces', [
            'name' => 'Photo Room',
            'slug' => 'photo-room',
            'type' => 'avr',
            'is_active' => true,
            'image' => $file,
        ]);

        $response->assertStatus(201);
        $space = Space::where('slug', 'photo-room')->first();
        $this->assertNotNull($space);
        $this->assertNotNull($space->image_path);
        Storage::disk('public')->assertExists($space->image_path);

        $url = $response->json('data.image_url');
        $this->assertIsString($url);
        $this->assertNotSame('', $url);
    }

    public function test_admin_can_replace_space_image_and_old_file_is_removed(): void
    {
        Storage::fake('public');

        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $first = UploadedFile::fake()->image('first.jpg', 400, 300);
        $this->post('/api/admin/spaces', [
            'name' => 'Swap Room',
            'slug' => 'swap-room',
            'type' => 'lobby',
            'is_active' => true,
            'image' => $first,
        ])->assertStatus(201);

        $space = Space::where('slug', 'swap-room')->first();
        $oldPath = $space->image_path;
        $this->assertNotNull($oldPath);
        Storage::disk('public')->assertExists($oldPath);

        $second = UploadedFile::fake()->image('second.jpg', 400, 300);
        $this->put("/api/admin/spaces/{$space->id}", [
            'name' => $space->name,
            'slug' => $space->slug,
            'type' => $space->type,
            'is_active' => '1',
            'capacity' => '',
            'image' => $second,
        ])->assertStatus(200);

        $space->refresh();
        $this->assertNotSame($oldPath, $space->image_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($space->image_path);
    }

    public function test_admin_can_clear_space_image(): void
    {
        Storage::fake('public');

        $admin = $this->makeAdminUser();
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->image('gone.jpg', 200, 200);
        $this->post('/api/admin/spaces', [
            'name' => 'Clear Room',
            'slug' => 'clear-room',
            'type' => 'avr',
            'is_active' => true,
            'image' => $file,
        ])->assertStatus(201);

        $space = Space::where('slug', 'clear-room')->first();
        $oldPath = $space->image_path;
        Storage::disk('public')->assertExists($oldPath);

        $this->put("/api/admin/spaces/{$space->id}", [
            'name' => $space->name,
            'slug' => $space->slug,
            'type' => $space->type,
            'is_active' => '1',
            'capacity' => '',
            'clear_image' => '1',
        ])->assertStatus(200);

        $space->refresh();
        $this->assertNull($space->image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }
}

