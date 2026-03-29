<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Ensures the committed login screen does not reintroduce a manual name field (stale builds are an ops concern).
 */
class LoginPageSourceTest extends TestCase
{
    public function test_login_jsx_does_not_contain_manual_name_field(): void
    {
        $path = base_path('resources/js/pages/Login.jsx');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringNotContainsString('Name (optional', $content);
        $this->assertStringNotContainsString('first-time', $content);
        $this->assertStringNotContainsString('setName', $content);
        $this->assertStringContainsString("api.post('/login', { email, password })", $content);
    }
}
