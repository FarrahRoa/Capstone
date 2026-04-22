<?php

namespace Tests\Unit;

use App\Support\SpaceGuidelineDetails;
use PHPUnit\Framework\TestCase;

class SpaceGuidelineDetailsTest extends TestCase
{
    public function test_normalize_returns_null_for_empty_input(): void
    {
        $this->assertNull(SpaceGuidelineDetails::normalize(null));
        $this->assertNull(SpaceGuidelineDetails::normalize([]));
    }

    public function test_for_api_returns_empty_array_when_stored_null(): void
    {
        $this->assertSame([], SpaceGuidelineDetails::forApi(null));
    }

    public function test_sanitize_internet_options_orders_and_deduplicates(): void
    {
        $san = SpaceGuidelineDetails::sanitizeInternetOptions(['School Wifi', 'LAN Cable', 'School Wifi']);
        $this->assertSame(['LAN Cable', 'School Wifi'], $san);
    }

    public function test_internet_options_exclusive_none_invalid_detects_mixed(): void
    {
        $this->assertTrue(SpaceGuidelineDetails::internetOptionsExclusiveNoneInvalid(['None', 'LAN Cable']));
        $this->assertFalse(SpaceGuidelineDetails::internetOptionsExclusiveNoneInvalid(['None']));
        $this->assertFalse(SpaceGuidelineDetails::internetOptionsExclusiveNoneInvalid(['LAN Cable', 'School Wifi']));
    }
}
