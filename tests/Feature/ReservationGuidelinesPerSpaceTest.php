<?php

namespace Tests\Feature;

use App\Models\PolicyDocument;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Support\SpaceGuidelineDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationGuidelinesPerSpaceTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
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

    private function studentUser(): User
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

    public function test_admin_can_save_general_guidelines_only_and_response_includes_spaces(): void
    {
        Space::create([
            'name' => 'AVR',
            'slug' => 'avr-g-'.uniqid(),
            'type' => 'avr',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $newGeneral = "General line A.\n\nGeneral line B.";
        $put = $this->putJson('/api/admin/policies/reservation-guidelines', [
            'content' => $newGeneral,
        ]);

        $put->assertOk();
        $put->assertJsonPath('message', 'Reservation guidelines saved.');
        $put->assertJsonPath('data.content', $newGeneral);
        $this->assertIsArray($put->json('data.spaces'));
        $this->assertNotEmpty($put->json('data.spaces'));

        $this->assertDatabaseHas('policy_documents', [
            'slug' => PolicyDocument::SLUG_RESERVATION_GUIDELINES,
            'content' => $newGeneral,
        ]);
    }

    public function test_admin_can_save_quantities_and_multi_internet_and_api_returns_canonical_details(): void
    {
        $avr = Space::create([
            'name' => 'AVR',
            'slug' => 'avr-ps-'.uniqid(),
            'type' => 'avr',
            'capacity' => 50,
            'is_active' => true,
        ]);
        $lobby = Space::create([
            'name' => 'Lobby',
            'slug' => 'lobby-ps-'.uniqid(),
            'type' => 'lobby',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $general = PolicyDocument::reservationGuidelines()->content;

        $put = $this->putJson('/api/admin/policies/reservation-guidelines', [
            'content' => $general,
            'space_guidelines' => [
                [
                    'space_id' => $avr->id,
                    'details' => [
                        'location' => 'Learning Commons — AVR wing',
                        'whiteboard_count' => 1,
                        'projector_count' => 0,
                        'computer_count' => 2,
                        'dvd_player_count' => 0,
                        'sound_system_count' => 1,
                        'internet_options' => ['LAN Cable', 'School Wifi'],
                        'others' => 'Bring adapters if needed.',
                    ],
                ],
                [
                    'space_id' => $lobby->id,
                    'details' => [
                        'location' => 'Ground floor lobby',
                        'projector_count' => 2,
                        'internet_options' => ['Boardroom Wifi'],
                    ],
                ],
            ],
        ]);

        $put->assertOk();

        $avr->refresh();
        $lobby->refresh();
        $this->assertSame('Learning Commons — AVR wing', $avr->guideline_details['location']);
        $this->assertSame(1, $avr->guideline_details['whiteboard_count']);
        $this->assertSame(0, $avr->guideline_details['projector_count']);
        $this->assertSame(2, $avr->guideline_details['computer_count']);
        $this->assertSame(0, $avr->guideline_details['dvd_player_count']);
        $this->assertSame(1, $avr->guideline_details['sound_system_count']);
        $this->assertSame(['LAN Cable', 'School Wifi'], $avr->guideline_details['internet_options']);

        $this->assertSame('Ground floor lobby', $lobby->guideline_details['location']);
        $this->assertSame(2, $lobby->guideline_details['projector_count']);
        $this->assertSame(['Boardroom Wifi'], $lobby->guideline_details['internet_options']);

        $spaces = $this->getJson('/api/spaces')->json('data');
        $avrRow = collect($spaces)->firstWhere('id', $avr->id);
        $lobbyRow = collect($spaces)->firstWhere('id', $lobby->id);
        $this->assertSame(0, $avrRow['guideline_details']['projector_count']);
        $this->assertSame(['LAN Cable', 'School Wifi'], $avrRow['guideline_details']['internet_options']);
        $this->assertSame(['Boardroom Wifi'], $lobbyRow['guideline_details']['internet_options']);
    }

    public function test_admin_update_one_space_does_not_erase_other_space_details(): void
    {
        $avr = Space::create([
            'name' => 'AVR',
            'slug' => 'avr-sw-'.uniqid(),
            'type' => 'avr',
            'capacity' => 10,
            'is_active' => true,
            'guideline_details' => [
                'location' => 'Keep AVR',
                'computer_count' => 3,
                'internet_options' => ['School Wifi'],
            ],
        ]);
        $lobby = Space::create([
            'name' => 'Lobby',
            'slug' => 'lobby-sw-'.uniqid(),
            'type' => 'lobby',
            'capacity' => 8,
            'is_active' => true,
            'guideline_details' => ['location' => 'Old lobby', 'whiteboard_count' => 0],
        ]);

        $admin = $this->adminUser();
        Sanctum::actingAs($admin);
        $general = PolicyDocument::reservationGuidelines()->content;

        $this->putJson('/api/admin/policies/reservation-guidelines', [
            'content' => $general,
            'space_guidelines' => [
                [
                    'space_id' => $lobby->id,
                    'details' => [
                        'location' => 'New lobby text',
                        'internet_options' => ['None'],
                    ],
                ],
            ],
        ])->assertOk();

        $avr->refresh();
        $lobby->refresh();
        $this->assertSame('Keep AVR', $avr->guideline_details['location']);
        $this->assertSame(3, $avr->guideline_details['computer_count']);
        $this->assertSame(['School Wifi'], $avr->guideline_details['internet_options']);
        $this->assertSame('New lobby text', $lobby->guideline_details['location']);
        $this->assertSame(['None'], $lobby->guideline_details['internet_options']);
        $this->assertArrayNotHasKey('whiteboard_count', $lobby->guideline_details);
    }

    public function test_student_reservation_guidelines_endpoint_includes_confab_payload_without_per_space_admin_list(): void
    {
        $student = $this->studentUser();
        Sanctum::actingAs($student);

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
        $this->assertArrayNotHasKey('spaces', $response->json('data'));
        $this->assertIsArray($response->json('data.confab_room_comparisons'));
    }

    public function test_admin_put_rejects_none_combined_with_other_internet_options(): void
    {
        $space = Space::create([
            'name' => 'Room',
            'slug' => 'room-none-'.uniqid(),
            'type' => 'boardroom',
            'capacity' => 5,
            'is_active' => true,
        ]);
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $resp = $this->putJson('/api/admin/policies/reservation-guidelines', [
            'content' => PolicyDocument::reservationGuidelines()->content,
            'space_guidelines' => [
                [
                    'space_id' => $space->id,
                    'details' => [
                        'internet_options' => ['None', 'LAN Cable'],
                    ],
                ],
            ],
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors('space_guidelines.0.details.internet_options');
    }

    public function test_admin_put_rejects_invalid_internet_option_string(): void
    {
        $space = Space::create([
            'name' => 'Room',
            'slug' => 'room-inv-'.uniqid(),
            'type' => 'boardroom',
            'capacity' => 5,
            'is_active' => true,
        ]);
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $resp = $this->putJson('/api/admin/policies/reservation-guidelines', [
            'content' => PolicyDocument::reservationGuidelines()->content,
            'space_guidelines' => [
                [
                    'space_id' => $space->id,
                    'details' => [
                        'internet_options' => ['Guest Wifi'],
                    ],
                ],
            ],
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors('space_guidelines.0.details.internet_options.0');
    }

    public function test_space_guideline_details_normalizer_drops_unknown_keys_and_keeps_counts(): void
    {
        $out = SpaceGuidelineDetails::normalize([
            'location' => 'Here',
            'hacked' => 'nope',
            'whiteboard_count' => 2,
            'internet_options' => ['School Wifi'],
        ]);
        $this->assertNotNull($out);
        $this->assertSame(['location' => 'Here', 'whiteboard_count' => 2, 'internet_options' => ['School Wifi']], $out);
    }

    public function test_legacy_yes_no_maps_to_counts_and_internet_no_maps_to_none(): void
    {
        $out = SpaceGuidelineDetails::normalize([
            'whiteboard' => 'yes',
            'computer' => 'no',
            'internet' => 'no',
        ]);
        $this->assertNotNull($out);
        $this->assertSame(1, $out['whiteboard_count']);
        $this->assertSame(0, $out['computer_count']);
        $this->assertSame(['None'], $out['internet_options']);
    }
}
