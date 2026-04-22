<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Ensures the reservation form wires dynamic general + per-space guideline display.
 */
class ReservationFormGuidelinesSourceTest extends TestCase
{
    public function test_reservation_form_includes_general_and_selected_space_guideline_blocks(): void
    {
        $path = base_path('resources/js/pages/ReservationForm.jsx');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('General guidelines', $content);
        $this->assertStringContainsString('spaceGuidelineDisplay', $content);
        $this->assertStringContainsString('spaceGuidelinesHasDetails', $content);
        $this->assertStringContainsString('room details', $content);
    }

    public function test_reservation_form_confab_pool_shows_shared_guidance_and_comparison_not_single_room_details(): void
    {
        $path = base_path('resources/js/pages/ReservationForm.jsx');
        $content = file_get_contents($path);
        $this->assertStringContainsString('is_confab_pool', $content);
        $this->assertStringContainsString('confab_guidelines_content', $content);
        $this->assertStringContainsString('confab_room_comparisons', $content);
        $this->assertStringContainsString('Confab guidelines', $content);
        $this->assertStringContainsString('How Confab assignment works', $content);
        $this->assertStringContainsString('Confab room details (compare numbered rooms)', $content);
        $this->assertStringContainsString('!isConfabPool && spaceGuidelinesHasDetails(selectedSpace)', $content);
    }
}
