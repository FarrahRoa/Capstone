<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentConfigClarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cors_and_sanctum_config_files_are_present_and_load(): void
    {
        $this->assertNotNull(config('cors.paths'));
        $this->assertIsArray(config('cors.paths'));
        $this->assertContains('api/*', config('cors.paths'));

        $this->assertNotNull(config('sanctum.guard'));
        $this->assertIsArray(config('sanctum.guard'));
    }

    public function test_env_example_documents_frontend_url_and_cors_vars(): void
    {
        $path = base_path('.env.example');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertIsString($contents);

        $this->assertStringContainsString('FRONTEND_URL=', $contents);
        $this->assertStringContainsString('CORS_ALLOWED_ORIGINS=', $contents);
        $this->assertStringContainsString('SANCTUM_STATEFUL_DOMAINS=', $contents);
    }
}

