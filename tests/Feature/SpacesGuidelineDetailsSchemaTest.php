<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ensures spaces.guideline_details exists after migrations and admin Guidelines APIs can select it.
 */
class SpacesGuidelineDetailsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_spaces_table_includes_guideline_details_column(): void
    {
        $this->assertTrue(Schema::hasColumn('spaces', 'guideline_details'));
    }

    public function test_admin_reservation_guidelines_endpoint_selects_guideline_details_without_error(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Test']
        );
        $admin = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);

        $slug = 'test-room-'.uniqid();
        Space::create([
            'name' => 'Test Room',
            'slug' => $slug,
            'type' => 'boardroom',
            'capacity' => 6,
            'is_active' => true,
            'guideline_details' => ['location' => 'North wing'],
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/policies/reservation-guidelines');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'content',
                'confab_guidelines_content',
                'confab_guidelines_updated_at',
                'confab_room_comparisons',
                'spaces',
            ],
        ]);

        $spaces = $response->json('data.spaces');
        $this->assertIsArray($spaces);
        $hit = collect($spaces)->firstWhere('slug', $slug);
        $this->assertNotNull($hit);
        $this->assertSame('North wing', $hit['guideline_details']['location']);
    }

    public function test_admin_put_reservation_guidelines_persists_guideline_details_on_space(): void
    {
        PolicyDocument::reservationGuidelines();

        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Test']
        );
        $admin = User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);

        $space = Space::create([
            'name' => 'AVR',
            'slug' => 'avr-schema-'.uniqid(),
            'type' => 'avr',
            'capacity' => 40,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $content = PolicyDocument::reservationGuidelines()->content;
        $put = $this->putJson('/api/admin/policies/reservation-guidelines', [
            'content' => $content,
            'space_guidelines' => [
                [
                    'space_id' => $space->id,
                    'details' => [
                        'location' => 'Learning Commons',
                        'internet_options' => ['LAN Cable', 'School Wifi'],
                        'projector_count' => 0,
                    ],
                ],
            ],
        ]);

        $put->assertOk();
        $space->refresh();
        $this->assertSame('Learning Commons', $space->guideline_details['location']);
        $this->assertSame(['LAN Cable', 'School Wifi'], $space->guideline_details['internet_options']);
        $this->assertSame(0, $space->guideline_details['projector_count']);
    }
}
