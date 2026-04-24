<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the committed login screen shape (OTP sign-in/sign-up + public schedule preview).
 */
class LoginPageSourceTest extends TestCase
{
    public function test_login_jsx_uses_otp_flow_and_schedule_overview(): void
    {
        $path = base_path('resources/js/pages/Login.jsx');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringNotContainsString('Name (optional', $content);
        $this->assertStringNotContainsString('first-time', $content);
        $this->assertStringNotContainsString('setName', $content);
        $this->assertStringNotContainsString("api.post('/login/google'", $content);
        $this->assertStringContainsString("api.post('/login'", $content);
        $this->assertStringContainsString('account_type', $content);
        $this->assertStringContainsString("'sign_in'", $content);
        $this->assertStringContainsString("'sign_up'", $content);
        $this->assertStringNotContainsString('expected_email', $content);
        $this->assertStringNotContainsString('xu-login-email', $content);
        $this->assertStringContainsString('PublicScheduleBoard', $content);
        $this->assertStringContainsString('Xavier University Library', $content);
        $this->assertStringContainsString('Employee/Staff', $content);
        $this->assertStringContainsString('xuLogotypeUrl', $content);
        $this->assertStringContainsString('2023%20XU%20Logotype%20Revision%20V2%20Stacked_Full%20Color.png', $content);
        $this->assertStringContainsString('alt="Xavier University Library Logo"', $content);
    }

    public function test_login_email_step_uses_distinct_sign_in_and_sign_up_loading_states(): void
    {
        $path = base_path('resources/js/pages/Login.jsx');
        $content = file_get_contents($path);

        $this->assertStringContainsString('submittingAction', $content);
        $this->assertStringContainsString("submittingAction === 'sign_in'", $content);
        $this->assertStringContainsString("submittingAction === 'sign_up'", $content);
        $this->assertStringContainsString('Signing in', $content);
        $this->assertStringContainsString('Signing up', $content);
        $this->assertStringNotContainsString('Sending', $content);
        $this->assertStringContainsString('setSubmittingAction(null)', $content);
    }

    public function test_public_login_schedule_board_is_read_only_in_source(): void
    {
        $path = base_path('resources/js/components/booking/BookingCalendar.jsx');
        $this->assertFileExists($path);
        $content = file_get_contents($path);

        // The public/login view must not link into reservation flows.
        $this->assertStringContainsString("api.get('/public/schedule-overview'", $content);
        $this->assertStringContainsString('readOnly', $content);
        $this->assertStringContainsString('Log in first to reserve a slot.', $content);
    }

    public function test_login_route_serves_spa_shell(): void
    {
        $this->get('/login')->assertOk()->assertSee('id="root"', false);
    }

    public function test_admin_login_page_uses_official_logotype_inside_card_and_sign_in_heading(): void
    {
        $path = base_path('resources/js/pages/AdminLogin.jsx');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('xuLogotypeUrl', $content);
        $this->assertStringContainsString('2023%20XU%20Logotype%20Revision%20V2%20Stacked_Full%20Color.png', $content);
        $this->assertStringContainsString('alt="Xavier University Library Logo"', $content);
        $this->assertStringContainsString('Admin Sign-In', $content);
        $this->assertStringNotContainsString('Admin sign in', $content);
        $this->assertStringContainsString('object-contain', $content);
    }
}
