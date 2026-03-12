<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Health check: the app responds on /up.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
