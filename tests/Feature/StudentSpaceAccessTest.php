<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Support\StudentSpaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentSpaceAccessTest extends TestCase
{
    use RefreshDatabase;

    private function studentUser(): User
    {
        $role = Role::firstOrCreate(['slug' => 'student'], ['name' => 'Student', 'description' => 'Test']);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_activated' => true,
            'user_type' => User::USER_TYPE_STUDENT,
            'email' => 'student-'.uniqid().'@my.xu.edu.ph',
        ]);
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

    public function test_student_spaces_index_returns_only_confab_pool(): void
    {
        $pool = Space::query()->firstWhere('is_confab_pool', true);
        $this->assertNotNull($pool);

        Space::create([
            'name' => 'AVR Test',
            'slug' => 'avr-student-test-'.uniqid(),
            'type' => Space::TYPE_AVR,
            'capacity' => 10,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->studentUser());

        $response = $this->getJson('/api/spaces');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([(int) $pool->id], array_map('intval', $ids));
    }

    public function test_faculty_spaces_index_includes_non_confab_spaces(): void
    {
        $avr = Space::create([
            'name' => 'AVR Faculty Test',
            'slug' => 'avr-faculty-test-'.uniqid(),
            'type' => Space::TYPE_AVR,
            'capacity' => 10,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->facultyUser());

        $response = $this->getJson('/api/spaces');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertTrue($ids->contains((int) $avr->id));
    }

    public function test_student_cannot_reserve_avr_via_api(): void
    {
        $user = $this->studentUser();
        Sanctum::actingAs($user);

        $avr = Space::create([
            'name' => 'AVR Blocked',
            'slug' => 'avr-blocked-'.uniqid(),
            'type' => Space::TYPE_AVR,
            'capacity' => 10,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $avr->id,
            'start_at' => now()->addDays(3)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'event_title' => 'Should fail',
            'participant_count' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['space_id']);
        $response->assertJsonFragment([
            'space_id' => [StudentSpaceAccess::CONFAB_ONLY_MESSAGE],
        ]);
    }

    public function test_student_cannot_query_avr_availability(): void
    {
        $user = $this->studentUser();
        Sanctum::actingAs($user);

        $avr = Space::create([
            'name' => 'AVR Avail',
            'slug' => 'avr-avail-'.uniqid(),
            'type' => Space::TYPE_AVR,
            'capacity' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/availability', [
            'space_id' => $avr->id,
            'date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => StudentSpaceAccess::CONFAB_ONLY_MESSAGE]);
    }

    public function test_student_with_med_flag_cannot_reserve_med_confab(): void
    {
        $user = $this->studentUser();
        $user->update(['med_confab_eligible' => true]);
        Sanctum::actingAs($user);

        $med = Space::create([
            'name' => 'Medical Confab Test',
            'slug' => 'med-student-'.uniqid(),
            'type' => Space::TYPE_MEDICAL_CONFAB,
            'capacity' => 10,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/reservations', [
            'space_id' => $med->id,
            'start_at' => now()->addDays(3)->setTime(9, 0)->toDateTimeString(),
            'end_at' => now()->addDays(3)->setTime(9, 30)->toDateTimeString(),
            'event_title' => 'Med',
            'participant_count' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['space_id']);
        $response->assertJsonFragment([
            'space_id' => [StudentSpaceAccess::CONFAB_ONLY_MESSAGE],
        ]);
    }

    public function test_student_showcase_spaces_returns_numbered_confabs_only_when_not_medical(): void
    {
        $user = $this->studentUser();
        $user->update(['med_confab_eligible' => false]);
        Sanctum::actingAs($user);

        Space::create([
            'name' => 'AVR Showcase Hidden',
            'slug' => 'avr-showcase-'.uniqid(),
            'type' => Space::TYPE_AVR,
            'capacity' => 10,
            'is_active' => true,
        ]);

        $med = Space::create([
            'name' => 'Medical Confab Hidden',
            'slug' => 'med-showcase-'.uniqid(),
            'type' => Space::TYPE_MEDICAL_CONFAB,
            'capacity' => 10,
            'is_active' => true,
        ]);

        $confab1 = Space::query()->firstWhere('slug', 'confab-1');
        $this->assertNotNull($confab1);

        $response = $this->getJson('/api/spaces?showcase=1');
        $response->assertOk();

        $rows = collect($response->json('data'));
        $slugs = $rows->pluck('slug')->all();

        $this->assertContains('confab-1', $slugs);
        $this->assertNotContains($med->slug, $slugs);
        $this->assertFalse($rows->contains(fn ($s) => ($s['type'] ?? '') === Space::TYPE_AVR));
        $this->assertFalse($rows->contains(fn ($s) => ($s['type'] ?? '') === Space::TYPE_BOARDROOM));
    }

    public function test_student_showcase_includes_medical_confab_when_eligible(): void
    {
        $user = $this->studentUser();
        $user->update(['med_confab_eligible' => true]);
        Sanctum::actingAs($user);

        $med = Space::create([
            'name' => 'Medical Confab Visible',
            'slug' => 'med-visible-'.uniqid(),
            'type' => Space::TYPE_MEDICAL_CONFAB,
            'capacity' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/spaces?showcase=1');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertTrue($ids->contains((int) $med->id));
    }

    public function test_faculty_showcase_param_returns_full_active_list(): void
    {
        $avr = Space::create([
            'name' => 'AVR Faculty Showcase',
            'slug' => 'avr-fac-showcase-'.uniqid(),
            'type' => Space::TYPE_AVR,
            'capacity' => 10,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->facultyUser());

        $response = $this->getJson('/api/spaces?showcase=1');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->map(fn ($id) => (int) $id);
        $this->assertTrue($ids->contains((int) $avr->id));
    }
}
