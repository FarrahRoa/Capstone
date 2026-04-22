<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSettingsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_own_name_and_mobile_number(): void
    {
        $this->seed(RoleSeeder::class);

        $studentRole = Role::where('slug', 'student')->first();
        $this->assertNotNull($studentRole);

        $user = User::create([
            'name' => 'Old Name',
            'email' => 'user1@my.xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'mobile_number' => '09170000000',
            'user_type' => User::USER_TYPE_STUDENT,
            'college_office' => 'College of Computer Studies',
        ]);

        Sanctum::actingAs($user);

        $resp = $this->patchJson('/api/me/account', [
            'name' => 'Farrah Ann',
            'mobile_number' => '+63 917 123 4567',
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('data.email', 'user1@my.xu.edu.ph');
        $resp->assertJsonPath('data.name', 'Farrah Ann');
        $resp->assertJsonPath('data.mobile_number', '+63 917 123 4567');

        $user->refresh();
        $this->assertSame('Farrah Ann', $user->name);
        $this->assertSame('+63 917 123 4567', $user->mobile_number);
    }

    public function test_update_account_rejects_invalid_payload(): void
    {
        $this->seed(RoleSeeder::class);
        $studentRole = Role::where('slug', 'student')->first();
        $this->assertNotNull($studentRole);

        $user = User::create([
            'name' => 'Old Name',
            'email' => 'user2@my.xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'mobile_number' => '09170000000',
        ]);

        Sanctum::actingAs($user);

        $resp = $this->patchJson('/api/me/account', [
            'name' => '',
            'mobile_number' => 'abc',
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors(['name', 'mobile_number']);
    }

    public function test_normal_user_cannot_submit_email_through_account_settings(): void
    {
        $this->seed(RoleSeeder::class);
        $studentRole = Role::where('slug', 'student')->first();
        $this->assertNotNull($studentRole);

        $user = User::create([
            'name' => 'Old Name',
            'email' => 'user3@my.xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'mobile_number' => '09170000000',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'New Name',
            'mobile_number' => '09179999999',
            'email' => 'hacker@my.xu.edu.ph',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        $user->refresh();
        $this->assertSame('user3@my.xu.edu.ph', $user->email);
    }

    public function test_unauthenticated_user_cannot_update_account(): void
    {
        $resp = $this->patchJson('/api/me/account', [
            'name' => 'New Name',
            'mobile_number' => '09179999999',
        ]);

        $resp->assertStatus(401);
    }

    public function test_admin_can_update_own_name_without_password_when_email_unchanged(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $user = User::create([
            'name' => 'Admin Old',
            'email' => 'admin-account@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $resp = $this->patchJson('/api/me/account', [
            'name' => 'Admin New',
            'email' => 'admin-account@xu.edu.ph',
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('data.name', 'Admin New');
        $resp->assertJsonPath('data.email', 'admin-account@xu.edu.ph');

        $user->refresh();
        $this->assertSame('Admin New', $user->name);
        $this->assertSame('admin-account@xu.edu.ph', $user->email);
    }

    public function test_admin_can_update_email_with_current_password_and_login_with_new_email(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-old@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Admin User',
            'email' => 'admin-new@xu.edu.ph',
            'current_password' => 'secret123',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('admin-new@xu.edu.ph', $user->email);

        $login = $this->postJson('/api/admin/login', [
            'email' => 'admin-new@xu.edu.ph',
            'password' => 'secret123',
        ]);
        $login->assertOk();
        $login->assertJsonPath('user.email', 'admin-new@xu.edu.ph');

        $this->postJson('/api/admin/login', [
            'email' => 'admin-old@xu.edu.ph',
            'password' => 'secret123',
        ])->assertStatus(404);
    }

    public function test_admin_email_change_rejects_wrong_password(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-pw@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Admin User',
            'email' => 'admin-pw2@xu.edu.ph',
            'current_password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertSame('admin-pw@xu.edu.ph', $user->email);
    }

    public function test_admin_email_uniqueness_is_enforced(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        User::create([
            'name' => 'Other Admin',
            'email' => 'taken@xu.edu.ph',
            'password' => Hash::make('x'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'unique-src@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Admin User',
            'email' => 'taken@xu.edu.ph',
            'current_password' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        $user->refresh();
        $this->assertSame('unique-src@xu.edu.ph', $user->email);
    }

    public function test_admin_cannot_set_email_outside_allowed_domains(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-dom@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Admin User',
            'email' => 'bad@gmail.com',
            'current_password' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_update_account_only_modifies_authenticated_user(): void
    {
        $this->seed(RoleSeeder::class);
        $studentRole = Role::where('slug', 'student')->first();
        $this->assertNotNull($studentRole);

        $alice = User::create([
            'name' => 'Alice',
            'email' => 'alice@my.xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'mobile_number' => '09170000001',
        ]);
        $bob = User::create([
            'name' => 'Bob',
            'email' => 'bob@my.xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'mobile_number' => '09170000002',
        ]);

        Sanctum::actingAs($alice);

        $this->patchJson('/api/me/account', [
            'name' => 'Alice Updated',
            'mobile_number' => '09179999999',
        ])->assertOk();

        $alice->refresh();
        $bob->refresh();
        $this->assertSame('Alice Updated', $alice->name);
        $this->assertSame('09179999999', $alice->mobile_number);
        $this->assertSame('Bob', $bob->name);
        $this->assertSame('09170000002', $bob->mobile_number);
    }

    public function test_normal_user_cannot_use_current_password_field(): void
    {
        $this->seed(RoleSeeder::class);
        $studentRole = Role::where('slug', 'student')->first();
        $this->assertNotNull($studentRole);

        $user = User::create([
            'name' => 'Student',
            'email' => 'stu@my.xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'mobile_number' => '09170000000',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Student',
            'mobile_number' => '09171111111',
            'current_password' => 'anything',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);
    }

    public function test_normal_user_cannot_submit_password_fields_through_account_settings(): void
    {
        $this->seed(RoleSeeder::class);
        $studentRole = Role::where('slug', 'student')->first();
        $this->assertNotNull($studentRole);

        $user = User::create([
            'name' => 'Student',
            'email' => 'stu-pw@my.xu.edu.ph',
            'password' => Hash::make('legacy'),
            'role_id' => $studentRole->id,
            'is_activated' => true,
            'mobile_number' => '09170000000',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Student',
            'mobile_number' => '09171111111',
            'password' => 'NewSecret12',
            'password_confirmation' => 'NewSecret12',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $user->refresh();
        $this->assertTrue(Hash::check('legacy', (string) $user->password));
    }

    public function test_admin_can_update_own_password_and_login_with_new_password(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-pw-only@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Admin User',
            'email' => 'admin-pw-only@xu.edu.ph',
            'current_password' => 'secret123',
            'password' => 'NewSecret12',
            'password_confirmation' => 'NewSecret12',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecret12', (string) $user->password));
        $this->assertFalse(Hash::check('secret123', (string) $user->password));

        $this->postJson('/api/admin/login', [
            'email' => 'admin-pw-only@xu.edu.ph',
            'password' => 'NewSecret12',
        ])->assertOk()->assertJsonPath('user.email', 'admin-pw-only@xu.edu.ph');

        $this->postJson('/api/admin/login', [
            'email' => 'admin-pw-only@xu.edu.ph',
            'password' => 'secret123',
        ])->assertStatus(401);
    }

    public function test_admin_password_change_rejects_confirmation_mismatch(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-mismatch@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Admin User',
            'email' => 'admin-mismatch@xu.edu.ph',
            'current_password' => 'secret123',
            'password' => 'NewSecret12',
            'password_confirmation' => 'OtherSecret12',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $user->refresh();
        $this->assertTrue(Hash::check('secret123', (string) $user->password));
    }

    public function test_admin_can_update_email_and_password_together_and_login(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-both-old@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Admin User',
            'email' => 'admin-both-new@xu.edu.ph',
            'current_password' => 'secret123',
            'password' => 'Combined99',
            'password_confirmation' => 'Combined99',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('admin-both-new@xu.edu.ph', $user->email);
        $this->assertTrue(Hash::check('Combined99', (string) $user->password));

        $this->postJson('/api/admin/login', [
            'email' => 'admin-both-new@xu.edu.ph',
            'password' => 'Combined99',
        ])->assertOk();

        $this->postJson('/api/admin/login', [
            'email' => 'admin-both-old@xu.edu.ph',
            'password' => 'Combined99',
        ])->assertStatus(404);
    }

    public function test_admin_password_change_rejects_wrong_current_password(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-wrong-curr@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Admin User',
            'email' => 'admin-wrong-curr@xu.edu.ph',
            'current_password' => 'not-the-password',
            'password' => 'NewSecret12',
            'password_confirmation' => 'NewSecret12',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertTrue(Hash::check('secret123', (string) $user->password));
    }

    public function test_admin_password_change_rejects_password_shorter_than_minimum(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-short@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Admin User',
            'email' => 'admin-short@xu.edu.ph',
            'current_password' => 'secret123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $user->refresh();
        $this->assertTrue(Hash::check('secret123', (string) $user->password));
    }

    public function test_patch_me_account_only_updates_authenticated_admin_not_peers(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $alice = User::create([
            'name' => 'Alice Admin',
            'email' => 'alice-admin@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);
        $bob = User::create([
            'name' => 'Bob Admin',
            'email' => 'bob-admin@xu.edu.ph',
            'password' => Hash::make('bobsecret'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);

        Sanctum::actingAs($alice);

        $this->patchJson('/api/me/account', [
            'name' => 'Alice Updated',
            'email' => 'alice-admin@xu.edu.ph',
            'current_password' => 'secret123',
            'password' => 'AliceNewPass',
            'password_confirmation' => 'AliceNewPass',
        ])->assertOk();

        $alice->refresh();
        $bob->refresh();
        $this->assertSame('Alice Updated', $alice->name);
        $this->assertTrue(Hash::check('AliceNewPass', (string) $alice->password));
        $this->assertSame('Bob Admin', $bob->name);
        $this->assertSame('bob-admin@xu.edu.ph', $bob->email);
        $this->assertTrue(Hash::check('bobsecret', (string) $bob->password));
    }
}
