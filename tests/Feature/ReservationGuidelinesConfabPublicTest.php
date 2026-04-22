<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Support\ConfabGuidelinesComparison;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationGuidelinesConfabPublicTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'student'],
            ['name' => 'Student', 'description' => 'Test']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Test']
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    public function test_reservation_guidelines_includes_confab_fields_and_numbered_room_comparisons(): void
    {
        $pool = Space::query()->firstWhere('is_confab_pool', true);
        $this->assertNotNull($pool, 'Seeder or migration should provide confab pool space.');

        $c1 = Space::create([
            'name' => 'Confab 901',
            'slug' => 'confab-901-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 10,
            'is_active' => true,
            'is_confab_pool' => false,
            'guideline_details' => [
                'location' => 'Floor 9 East',
                'whiteboard_count' => 2,
                'internet_options' => ['School Wifi'],
            ],
        ]);
        $c2 = Space::create([
            'name' => 'Confab 902',
            'slug' => 'confab-902-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 6,
            'is_active' => true,
            'is_confab_pool' => false,
            'guideline_details' => [
                'location' => 'Floor 9 West',
                'projector_count' => 1,
            ],
        ]);

        Sanctum::actingAs($this->student());

        $response = $this->getJson('/api/reservation-guidelines');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'slug',
                'content',
                'updated_at',
                'confab_guidelines_content',
                'confab_guidelines_updated_at',
                'confab_room_comparisons',
            ],
        ]);

        $this->assertNotSame('', $response->json('data.confab_guidelines_content'));
        $rows = $response->json('data.confab_room_comparisons');
        $this->assertIsArray($rows);

        $names = collect($rows)->pluck('name')->all();
        $this->assertContains('Confab 901', $names);
        $this->assertContains('Confab 902', $names);
        $this->assertNotContains($pool->name, $names);

        $hit1 = collect($rows)->firstWhere('name', 'Confab 901');
        $this->assertSame('Floor 9 East', $hit1['guideline_details']['location']);
        $this->assertSame(2, $hit1['guideline_details']['whiteboard_count']);
        $this->assertSame(['School Wifi'], $hit1['guideline_details']['internet_options']);
    }

    public function test_confab_room_comparisons_excludes_medical_confab_rooms(): void
    {
        Space::create([
            'name' => 'Medical Confab Z',
            'slug' => 'med-conf-z-'.uniqid(),
            'type' => Space::TYPE_MEDICAL_CONFAB,
            'capacity' => 4,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        Sanctum::actingAs($this->student());
        $rows = $this->getJson('/api/reservation-guidelines')->json('data.confab_room_comparisons');
        $this->assertFalse(collect($rows)->pluck('name')->contains('Medical Confab Z'));
    }

    public function test_admin_can_update_confab_guidelines_and_public_payload_reflects_change(): void
    {
        PolicyDocument::confabReservationGuidelines();

        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $general = PolicyDocument::reservationGuidelines()->content;
        $custom = "POOL-SPECIFIC\n\nSecond paragraph for confab requests only.";

        $put = $this->putJson('/api/admin/policies/reservation-guidelines', [
            'content' => $general,
            'confab_guidelines_content' => $custom,
        ]);

        $put->assertOk();
        $put->assertJsonPath('data.confab_guidelines_content', $custom);

        $this->assertDatabaseHas('policy_documents', [
            'slug' => PolicyDocument::SLUG_CONFAB_RESERVATION_GUIDELINES,
            'content' => $custom,
        ]);

        Sanctum::actingAs($this->student());
        $this->getJson('/api/reservation-guidelines')
            ->assertOk()
            ->assertJsonPath('data.confab_guidelines_content', $custom);
    }

    public function test_confab_room_comparisons_payload_helper_matches_controller_listing(): void
    {
        Space::create([
            'name' => 'Confab Compare A',
            'slug' => 'confab-cmp-a-'.uniqid(),
            'type' => Space::TYPE_CONFAB,
            'capacity' => 3,
            'is_active' => true,
            'is_confab_pool' => false,
        ]);

        $fromController = ConfabGuidelinesComparison::physicalConfabRoomsPayload();
        $this->assertTrue(collect($fromController)->pluck('name')->contains('Confab Compare A'));
    }
}
