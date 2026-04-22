<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TrustedDevice;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrustedDevicesTableSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_devices_table_exists_after_migrations(): void
    {
        $this->assertTrue(
            Schema::hasTable('trusted_devices'),
            'Run `php artisan migrate` so the trusted_devices migration is applied to your database.'
        );
    }

    public function test_trusted_devices_table_has_required_columns(): void
    {
        $columns = Schema::getColumnListing('trusted_devices');
        foreach (['id', 'user_id', 'token_hash', 'user_agent', 'last_used_at', 'expires_at', 'revoked_at', 'created_at', 'updated_at'] as $required) {
            $this->assertContains(
                $required,
                $columns,
                "trusted_devices is missing column `{$required}`; ensure migrations are current."
            );
        }
    }

    public function test_trusted_device_row_can_be_inserted_like_otp_verify_path(): void
    {
        $this->seed(RoleSeeder::class);
        $student = Role::where('slug', 'student')->first();
        $this->assertNotNull($student);

        $user = User::create([
            'name' => 'Schema Test User',
            'email' => 'schematest@my.xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $student->id,
            'is_activated' => true,
        ]);

        $plain = bin2hex(random_bytes(32));
        $device = TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($plain),
            'user_agent' => 'PHPUnit',
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertDatabaseHas('trusted_devices', [
            'id' => $device->id,
            'user_id' => $user->id,
        ]);
        $this->assertTrue(Hash::check($plain, $device->fresh()->token_hash));
    }
}
