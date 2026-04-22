<?php

namespace Tests\Feature;

use Tests\TestCase;

class NavbarAccountEntrySourceTest extends TestCase
{
    public function test_layout_includes_account_settings_entry(): void
    {
        $path = base_path('resources/js/components/Layout.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('Account Settings', $content);
        $this->assertStringContainsString('to="/account"', $content);
    }
}

