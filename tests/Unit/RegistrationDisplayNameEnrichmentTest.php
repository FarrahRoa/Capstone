<?php

namespace Tests\Unit;

use App\Support\RegistrationDisplayName;
use PHPUnit\Framework\TestCase;

class RegistrationDisplayNameEnrichmentTest extends TestCase
{
    public function test_needs_enrichment_for_fallback_only(): void
    {
        $this->assertTrue(RegistrationDisplayName::needsEnrichment('XU User'));
        $this->assertTrue(RegistrationDisplayName::needsEnrichment('  XU User  '));
        $this->assertFalse(RegistrationDisplayName::needsEnrichment('Juan Cruz'));
        $this->assertFalse(RegistrationDisplayName::needsEnrichment(null));
    }
}
