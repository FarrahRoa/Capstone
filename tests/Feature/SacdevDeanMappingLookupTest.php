<?php

namespace Tests\Feature;

use App\Mail\ReservationVerificationMail;
use App\Models\DeanEmailMapping;
use App\Models\Office;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Support\ReservationDeanRouting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SacdevDeanMappingLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-05-16 10:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function facultyUser(): User
    {
        $role = Role::firstOrCreate(['slug' => 'faculty'], ['name' => 'Faculty', 'description' => 'Test']);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_FACULTY_STAFF,
            'email' => 'faculty-'.uniqid().'@xu.edu.ph',
        ]);
    }

    public function test_finds_sacdev_by_affiliation_name_when_office_row_exists_without_office_id(): void
    {
        Office::query()->create(['name' => 'SACDEV']);

        DeanEmailMapping::query()->create([
            'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
            'affiliation_name' => 'SACDEV',
            'approver_email' => 'sacdev.by.name@test.xu.edu.ph',
            'is_active' => true,
        ]);

        $mapping = ReservationDeanRouting::activeMappingForOrganizationAvrLobby();

        $this->assertNotNull($mapping);
        $this->assertSame('sacdev.by.name@test.xu.edu.ph', $mapping->approver_email);
    }

    public function test_finds_sacdev_by_office_code_column(): void
    {
        DeanEmailMapping::query()->create([
            'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
            'affiliation_name' => 'SACDEV',
            'office_code' => 'SACDEV',
            'approver_email' => 'sacdev.by.code@test.xu.edu.ph',
            'is_active' => true,
        ]);

        $mapping = DeanEmailMapping::findActiveForOfficeCode('sacdev');

        $this->assertNotNull($mapping);
        $this->assertSame('sacdev.by.code@test.xu.edu.ph', $mapping->approver_email);
    }

    public function test_admin_store_links_office_id_and_office_code_for_sacdev(): void
    {
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'Test']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_activated' => true]);
        Sanctum::actingAs($admin);

        Office::query()->create(['name' => 'SACDEV']);

        $response = $this->postJson('/api/admin/dean-email-mappings', [
            'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
            'affiliation_name' => 'SACDEV',
            'approver_email' => 'sacdev.admin.saved@test.xu.edu.ph',
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        $row = DeanEmailMapping::query()->where('approver_email', 'sacdev.admin.saved@test.xu.edu.ph')->first();
        $this->assertNotNull($row);
        $this->assertSame('SACDEV', $row->office_code);
        $this->assertNotNull($row->office_id);
        $this->assertTrue(ReservationDeanRouting::activeMappingForOrganizationAvrLobby() !== null);
    }

    public function test_user_avr_organization_reservation_succeeds_when_sacdev_mapped_by_name_only(): void
    {
        Mail::fake();
        Office::query()->create(['name' => 'SACDEV']);
        DeanEmailMapping::query()->create([
            'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
            'affiliation_name' => 'SACDEV',
            'approver_email' => 'sacdev.route@test.xu.edu.ph',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->facultyUser());
        $avr = Space::create([
            'name' => 'AVR',
            'slug' => 'avr-'.uniqid(),
            'type' => Space::TYPE_AVR,
            'capacity' => 70,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $avr->id,
            'start_at' => '2026-05-20T08:00:00+08:00',
            'end_at' => '2026-05-20T12:00:00+08:00',
            'event_title' => 'Org event',
            'participant_count' => 25,
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(201);
        $response->assertJsonMissingValidationErrors(['event_request_type']);
        Mail::assertSent(ReservationVerificationMail::class);
    }

    public function test_inactive_sacdev_mapping_returns_validation_error(): void
    {
        DeanEmailMapping::query()->create([
            'affiliation_type' => DeanEmailMapping::TYPE_OFFICE_DEPARTMENT,
            'affiliation_name' => 'SACDEV',
            'office_code' => 'SACDEV',
            'approver_email' => 'inactive@test.xu.edu.ph',
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->facultyUser());
        $avr = Space::create([
            'name' => 'AVR',
            'slug' => 'avr-inactive-'.uniqid(),
            'type' => Space::TYPE_AVR,
            'capacity' => 70,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', array_merge([
            'space_id' => $avr->id,
            'start_at' => '2026-05-20T08:00:00+08:00',
            'end_at' => '2026-05-20T12:00:00+08:00',
            'event_title' => 'Org event',
            'participant_count' => 5,
        ], $this->organizationEventAudiencePayload()));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['event_request_type']);
        $this->assertStringContainsString(
            'SACDEV',
            (string) $response->json('errors.event_request_type.0')
        );
    }
}
