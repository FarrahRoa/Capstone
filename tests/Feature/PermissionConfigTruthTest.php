<?php

namespace Tests\Feature;

use Tests\TestCase;

class PermissionConfigTruthTest extends TestCase
{
    /**
     * Removed as fake/stale: never enforced on routes or referenced in active UI.
     */
    private const REMOVED_FAKE_PERMISSIONS = [
        'reservation.cancel_own',
        'booking_history.view_all',
        'roles.manage',
        'spaces.view_active',
    ];

    /**
     * Permissions required by route middleware or FormRequest::authorize (api.php, StoreReservationRequest).
     */
    private const PERMISSIONS_REQUIRED_BY_ACTIVE_BACKEND = [
        'reservation.create',
        'reservation.view_all',
        'reservation.approve',
        'reservation.reject',
        'reservation.override',
        'spaces.manage',
        'users.manage',
        'reports.view',
        'reports.export',
        'policies.manage',
        'system.cloud_sync',
    ];

    /**
     * Permissions used by the SPA for PrivateRoute / Layout (resources/js).
     */
    private const PERMISSIONS_REQUIRED_BY_ACTIVE_FRONTEND = [
        'calendar.view',
        'reservation.view_own',
    ];

    /**
     * @return list<string>
     */
    private function allConfiguredPermissions(): array
    {
        $roles = config('permissions.roles', []);
        $flat = [];
        foreach ($roles as $permissions) {
            if (!is_array($permissions)) {
                continue;
            }
            foreach ($permissions as $p) {
                $flat[] = $p;
            }
        }

        return array_values(array_unique($flat));
    }

    public function test_removed_fake_permissions_not_in_config(): void
    {
        $all = $this->allConfiguredPermissions();
        foreach (self::REMOVED_FAKE_PERMISSIONS as $fake) {
            $this->assertNotContains(
                $fake,
                $all,
                "Stale permission {$fake} must not appear in config/permissions.php."
            );
        }
    }

    public function test_all_backend_enforced_permissions_are_grantable(): void
    {
        $all = $this->allConfiguredPermissions();
        foreach (self::PERMISSIONS_REQUIRED_BY_ACTIVE_BACKEND as $required) {
            $this->assertContains(
                $required,
                $all,
                "Permission {$required} is used by the API but is not granted to any role in config."
            );
        }
    }

    public function test_all_frontend_gated_permissions_are_grantable(): void
    {
        $all = $this->allConfiguredPermissions();
        foreach (self::PERMISSIONS_REQUIRED_BY_ACTIVE_FRONTEND as $required) {
            $this->assertContains(
                $required,
                $all,
                "Permission {$required} is used by the SPA but is not granted to any role in config."
            );
        }
    }

    public function test_admin_role_retains_admin_only_capabilities(): void
    {
        $admin = config('permissions.roles.admin', []);
        $this->assertIsArray($admin);
        foreach (['reservation.override', 'reports.export', 'users.manage', 'policies.manage', 'system.cloud_sync'] as $p) {
            $this->assertContains($p, $admin, "Admin role must include {$p}.");
        }
    }

    public function test_librarian_cannot_export_reports_per_config(): void
    {
        $librarian = config('permissions.roles.librarian', []);
        $this->assertIsArray($librarian);
        $this->assertNotContains('reports.export', $librarian);
    }
}
