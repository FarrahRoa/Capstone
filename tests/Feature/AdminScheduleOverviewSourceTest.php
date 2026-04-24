<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Ensures admin schedule overview requests operational labels from the availability API.
 */
class AdminScheduleOverviewSourceTest extends TestCase
{
    public function test_admin_schedule_overview_requests_operational_availability_labels(): void
    {
        $path = base_path('resources/js/components/booking/AdminScheduleOverview.jsx');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString("operational: 1", $content);
        $this->assertStringContainsString("'/availability'", $content);
    }

    public function test_home_dashboard_fetches_operational_spaces_when_admin_schedule_viewer(): void
    {
        $path = base_path('resources/js/components/dashboard/HomeDashboardDeferredSections.jsx');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString("operational: 1", $content);
        $this->assertStringContainsString('adminSchedule', $content);
    }

    public function test_calendar_page_fetches_operational_spaces_for_admin_schedule_viewer(): void
    {
        $path = base_path('resources/js/pages/Calendar.jsx');
        $content = file_get_contents($path);
        $this->assertStringContainsString("operational: 1", $content);
        $this->assertStringContainsString('adminSchedule', $content);
    }
}
