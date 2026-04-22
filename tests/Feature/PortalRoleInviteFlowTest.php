<?php

namespace Tests\Feature;

use App\Mail\LibrarianInviteMail;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalRoleInviteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        return User::factory()->create([
            'email' => 'admin@xu.edu.ph',
            'password' => Hash::make('secret123'),
            'role_id' => $adminRole->id,
            'is_activated' => true,
        ]);
    }

    public function test_invite_requires_email_and_role_slug(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users/invite-portal-role', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'role_slug']);
        Mail::assertNothingSent();
    }

    public function test_invite_rejects_invalid_role_slug(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users/invite-portal-role', [
            'email' => 'someone@xu.edu.ph',
            'role_slug' => 'admin',
        ])->assertStatus(422)->assertJsonValidationErrors(['role_slug']);

        Mail::assertNothingSent();
    }

    public function test_librarian_invite_requires_xu_domain(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users/invite-portal-role', [
            'email' => 'lib@my.xu.edu.ph',
            'role_slug' => 'librarian',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_student_assistant_invite_requires_my_xu_domain(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users/invite-portal-role', [
            'email' => 'sa@xu.edu.ph',
            'role_slug' => 'student_assistant',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_admin_can_invite_librarian_password_is_hashed_and_email_contains_credentials(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $resp = $this->postJson('/api/admin/users/invite-portal-role', [
            'email' => 'new.librarian@xu.edu.ph',
            'role_slug' => 'librarian',
        ]);
        $resp->assertOk();
        $resp->assertJsonStructure(['data' => ['email', 'role', 'invited_at']]);
        $this->assertArrayNotHasKey('password', $resp->json('data') ?? []);

        $user = User::where('email', 'new.librarian@xu.edu.ph')->firstOrFail();
        $user->loadMissing('role');
        $this->assertSame('librarian', $user->role?->slug);

        $plain = null;
        Mail::assertSent(LibrarianInviteMail::class, function (LibrarianInviteMail $mail) use ($user, &$plain) {
            $plain = $mail->temporaryPassword;
            return $mail->hasTo($user->email)
                && str_contains($mail->render(), $user->email)
                && str_contains($mail->render(), 'Librarian')
                && str_contains($mail->render(), $plain)
                && str_contains($mail->render(), '/admin/login');
        });
        $this->assertIsString($plain);
        $this->assertGreaterThanOrEqual(8, strlen($plain));
        $this->assertTrue(Hash::check($plain, (string) $user->password));
        $this->assertNull($user->admin_invite_token_hash);
        $this->assertNull($user->admin_invite_expires_at);

        $login = $this->postJson('/api/admin/login', [
            'email' => 'new.librarian@xu.edu.ph',
            'password' => $plain,
        ]);
        $login->assertOk()->assertJsonPath('user.role.slug', 'librarian');
    }

    public function test_admin_can_invite_student_assistant_and_can_change_password_in_account_settings(): void
    {
        Mail::fake();
        $this->seed(RoleSeeder::class);

        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users/invite-portal-role', [
            'email' => 'new.sa@my.xu.edu.ph',
            'role_slug' => 'student_assistant',
        ])->assertOk();

        $user = User::where('email', 'new.sa@my.xu.edu.ph')->firstOrFail();
        $user->loadMissing('role');
        $this->assertSame('student_assistant', $user->role?->slug);

        $plain = null;
        Mail::assertSent(LibrarianInviteMail::class, function (LibrarianInviteMail $mail) use (&$plain) {
            $plain = $mail->temporaryPassword;
            return str_contains($mail->render(), 'Student Assistant')
                && str_contains($mail->render(), $plain);
        });
        $this->assertIsString($plain);

        $this->postJson('/api/admin/login', [
            'email' => 'new.sa@my.xu.edu.ph',
            'password' => $plain,
        ])->assertOk()->assertJsonPath('user.role.slug', 'student_assistant');

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/account', [
            'name' => 'Student Assistant User',
            'email' => 'new.sa@my.xu.edu.ph',
            'current_password' => $plain,
            'password' => 'UpdatedPass99',
            'password_confirmation' => 'UpdatedPass99',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('UpdatedPass99', (string) $user->password));

        $this->postJson('/api/admin/login', [
            'email' => 'new.sa@my.xu.edu.ph',
            'password' => 'UpdatedPass99',
        ])->assertOk();

        $this->postJson('/api/admin/login', [
            'email' => 'new.sa@my.xu.edu.ph',
            'password' => $plain,
        ])->assertStatus(401);
    }
}
