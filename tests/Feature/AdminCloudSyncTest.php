<?php

namespace Tests\Feature;

use App\Models\CloudSyncEvent;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCloudSyncTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        return User::factory()->create([
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);
    }

    private function librarianUser(): User
    {
        $this->seed(RoleSeeder::class);
        $role = Role::where('slug', 'librarian')->first();
        $this->assertNotNull($role);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
        ]);
    }

    private function makeFallbackReservation(): Reservation
    {
        $this->seed(RoleSeeder::class);
        $studentRole = Role::where('slug', 'student')->first();
        $user = User::factory()->create(['role_id' => $studentRole->id, 'is_activated' => true]);
        $space = Space::create([
            'name' => 'Sync Room',
            'slug' => 'sync-room-'.uniqid(),
            'type' => 'avr',
            'capacity' => 5,
            'is_active' => true,
        ]);

        $r = Reservation::create([
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'status' => Reservation::STATUS_PENDING_APPROVAL,
            'purpose' => 'Fallback',
        ]);

        \Illuminate\Support\Facades\DB::table('reservations')->where('id', $r->id)->update([
            'cloud_sync_origin' => Reservation::CLOUD_SYNC_ORIGIN_LOCAL_FALLBACK,
            'cloud_synced_at' => null,
        ]);

        return $r->fresh();
    }

    public function test_librarian_cannot_access_cloud_sync_status(): void
    {
        Sanctum::actingAs($this->librarianUser());
        $this->getJson('/api/admin/cloud-sync/status')->assertStatus(403);
    }

    public function test_admin_can_load_cloud_sync_status(): void
    {
        Sanctum::actingAs($this->adminUser());
        $resp = $this->getJson('/api/admin/cloud-sync/status');
        $resp->assertOk();
        $resp->assertJsonPath('data.pending_local_changes', 0);
        $resp->assertJsonPath('data.automatic_sync.state', 'disabled');
        $resp->assertJsonPath('data.cloud_change_feed.available', false);
        $this->assertFalse($resp->json('data.push_url_configured'));
    }

    public function test_manual_upload_without_push_url_skips_pending_and_does_not_mark_synced(): void
    {
        config(['cloud_sync.push_url' => null]);
        $this->makeFallbackReservation();

        Sanctum::actingAs($this->adminUser());
        $this->getJson('/api/admin/cloud-sync/status')->assertJsonPath('data.pending_local_changes', 1);

        $resp = $this->postJson('/api/admin/cloud-sync/upload');
        $resp->assertOk();
        $this->assertSame(0, (int) $resp->json('data.succeeded'));
        $this->assertSame(1, (int) $resp->json('data.skipped'));

        $this->getJson('/api/admin/cloud-sync/status')->assertJsonPath('data.pending_local_changes', 1);

        $this->assertDatabaseHas('cloud_sync_events', [
            'event_type' => CloudSyncEvent::TYPE_RESERVATION_PUSH_SKIPPED,
        ]);
    }

    public function test_http_push_success_then_second_upload_is_idempotent(): void
    {
        Http::fake([
            'https://cloud-primary.test/sync' => Http::response(['ok' => true], 201),
        ]);
        config(['cloud_sync.push_url' => 'https://cloud-primary.test/sync', 'cloud_sync.push_token' => 'secret']);

        $reservation = $this->makeFallbackReservation();

        Sanctum::actingAs($this->adminUser());
        $this->postJson('/api/admin/cloud-sync/upload')->assertOk()->assertJsonPath('data.succeeded', 1);

        $reservation->refresh();
        $this->assertNotNull($reservation->cloud_synced_at);

        Http::fake([
            'https://cloud-primary.test/sync' => Http::response(['error' => 'should not be called'], 500),
        ]);

        $this->postJson('/api/admin/cloud-sync/upload')->assertOk()->assertJsonPath('data.processed', 0);

        $this->assertCount(1, Http::recorded());
    }

    public function test_http_409_duplicate_treated_as_success_and_marks_synced(): void
    {
        Http::fake([
            'https://cloud-primary.test/sync' => Http::response(['duplicate' => true], 409),
        ]);
        config(['cloud_sync.push_url' => 'https://cloud-primary.test/sync']);

        $this->makeFallbackReservation();

        Sanctum::actingAs($this->adminUser());
        $this->postJson('/api/admin/cloud-sync/upload')->assertOk()->assertJsonPath('data.succeeded', 1);

        $this->assertDatabaseHas('cloud_sync_events', [
            'event_type' => CloudSyncEvent::TYPE_RESERVATION_PUSH_SUCCESS,
        ]);
    }

    public function test_http_failure_records_failed_event_and_returns_422(): void
    {
        Http::fake([
            'https://cloud-primary.test/sync' => Http::response(['error' => 'bad'], 500),
        ]);
        config(['cloud_sync.push_url' => 'https://cloud-primary.test/sync']);

        $this->makeFallbackReservation();

        Sanctum::actingAs($this->adminUser());
        $this->postJson('/api/admin/cloud-sync/upload')->assertStatus(422)->assertJsonPath('data.failed', 1);

        $this->assertDatabaseHas('cloud_sync_events', [
            'event_type' => CloudSyncEvent::TYPE_RESERVATION_PUSH_FAILED,
        ]);
    }

    public function test_reachability_url_used_for_status_when_configured(): void
    {
        Http::fake([
            'https://cloud-primary.test/up' => Http::response('', 200),
        ]);
        config(['cloud_sync.reachability_url' => 'https://cloud-primary.test/up']);

        Sanctum::actingAs($this->adminUser());
        $this->getJson('/api/admin/cloud-sync/status')->assertOk()->assertJsonPath('data.cloud_reachable', true);
    }

    public function test_automatic_sync_shows_idle_when_flag_enabled(): void
    {
        config(['cloud_sync.auto_sync_enabled' => true]);
        Sanctum::actingAs($this->adminUser());
        $this->getJson('/api/admin/cloud-sync/status')->assertJsonPath('data.automatic_sync.state', 'idle');
    }
}
