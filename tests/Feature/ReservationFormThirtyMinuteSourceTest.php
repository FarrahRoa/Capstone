<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards reservation form UX: time inputs align to the same :00 / :30 rule as the API.
 */
class ReservationFormThirtyMinuteSourceTest extends TestCase
{
    public function test_reservation_form_uses_half_hour_select_picker_not_native_time_input(): void
    {
        $path = base_path('resources/js/pages/ReservationForm.jsx');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString("useState('09:00')", $content);
        $this->assertStringContainsString('HalfHourWallClockSelect', $content);
        $this->assertStringContainsString('reservationBookingTimes', $content);
        $this->assertStringNotContainsString('type="time"', $content, 'Native time inputs show a full minute column in many browsers.');
        $this->assertStringNotContainsString('\\d{2}:15', $content);

        $util = base_path('resources/js/utils/reservationBookingTimes.js');
        $this->assertFileExists($util);
        $utilContent = file_get_contents($util);
        $this->assertStringContainsString('\\d{2}:(00|30)', $utilContent);
        $this->assertStringContainsString('halfHourHhmmFromOptionalQueryParam', $utilContent);

        $pickerUtil = base_path('resources/js/utils/halfHourWallClockInput.js');
        $this->assertFileExists($pickerUtil);
        $pickerBody = file_get_contents($pickerUtil);
        $this->assertStringContainsString("['00', '30']", $pickerBody);
    }

    public function test_my_reservations_edit_modal_uses_same_half_hour_select_picker(): void
    {
        $path = base_path('resources/js/pages/MyReservations.jsx');
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('HalfHourWallClockSelect', $content);
        $this->assertStringNotContainsString('type="time"', $content);
        $this->assertSame(6, substr_count($content, 'HalfHourWallClockSelect'), 'Edit modal should use the shared picker on every time field.');
    }

    public function test_half_hour_wall_clock_component_minute_options_are_only_zero_and_thirty(): void
    {
        $cmp = base_path('resources/js/components/booking/HalfHourWallClockSelect.jsx');
        $this->assertFileExists($cmp);
        $body = file_get_contents($cmp);
        $this->assertStringContainsString('RESERVATION_TIME_MINUTE_CHOICES', $body);
        $this->assertStringNotContainsString('type="time"', $body);
    }

    public function test_space_eligibility_treats_all_spaces_as_half_hour_grid(): void
    {
        $path = base_path('resources/js/utils/spaceEligibility.js');
        $content = file_get_contents($path);
        $this->assertStringContainsString('return Boolean(space);', $content);
    }
}
