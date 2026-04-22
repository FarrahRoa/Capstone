<?php

namespace Tests\Unit;

use App\Support\ReportPdfPresenter;
use Tests\TestCase;

class ReportPdfPresenterTest extends TestCase
{
    public function test_peak24_includes_all_hours_and_reads_padded_keys(): void
    {
        $charts = ReportPdfPresenter::compile([
            'peak_hours' => ['09' => 2, '14' => 1],
        ]);

        $this->assertCount(24, $charts['peak']);
        $this->assertSame(2, $charts['peak'][9]['value']);
        $this->assertSame(1, $charts['peak'][14]['value']);
        $this->assertSame(0, $charts['peak'][0]['value']);
        $this->assertSame(2, $charts['peak_max']);
        $this->assertSame('12:00 AM', $charts['peak'][0]['label']);
        $this->assertSame('9:00 AM', $charts['peak'][9]['label']);
        $this->assertSame('2:00 PM', $charts['peak'][14]['label']);
    }

    public function test_bucket_items_sorts_and_drops_zero(): void
    {
        $charts = ReportPdfPresenter::compile([
            'student_college' => ['B' => 0, 'A' => 3, 'C' => 3],
        ]);

        $this->assertSame([
            ['label' => 'A', 'value' => 3],
            ['label' => 'C', 'value' => 3],
        ], array_slice($charts['student_college'], 0, 2));
        $this->assertSame(3, $charts['student_college_max']);
    }

    public function test_year_level_stack_percentages_sum_to_one_hundred(): void
    {
        $charts = ReportPdfPresenter::compile([
            'student_year_level' => ['1st' => 25, '2nd' => 75],
        ]);

        $sum = array_sum(array_column($charts['year_level_stack'], 'pct'));
        $this->assertEqualsWithDelta(100.0, $sum, 0.05);
    }
}
