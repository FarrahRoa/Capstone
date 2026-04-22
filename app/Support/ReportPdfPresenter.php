<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Normalizes report payload fields for PDF chart-style rendering (DomPDF-safe HTML/CSS).
 */
final class ReportPdfPresenter
{
    private const COLORS = ['#283971', '#3a52a3', '#b99430', '#4a6bc9', '#1e2d5c', '#5c6ba8', '#8a7340', '#6b7db3'];

    /**
     * @param  array<string, mixed>  $data  Report JSON payload
     * @return array<string, mixed>
     */
    public static function compile(array $data): array
    {
        $collegeOffice = self::bucketItems($data['reservations_by_college_office'] ?? []);
        $studentCollege = self::bucketItems($data['student_college'] ?? []);
        $facultyOffice = self::bucketItems($data['faculty_staff_office'] ?? []);
        $yearLevel = self::bucketItems($data['student_year_level'] ?? []);
        $rooms = self::roomItems($data['room_utilization'] ?? []);
        $peak = self::peak24($data['peak_hours'] ?? []);
        $peakMax = max(1, ...array_column($peak, 'value'));

        return [
            'college_office' => $collegeOffice,
            'college_office_max' => self::maxValue($collegeOffice),
            'student_college' => $studentCollege,
            'student_college_max' => self::maxValue($studentCollege),
            'faculty_office' => $facultyOffice,
            'faculty_office_max' => self::maxValue($facultyOffice),
            'year_level' => $yearLevel,
            'year_level_stack' => self::stackSegments($yearLevel),
            'rooms' => $rooms,
            'rooms_max' => self::maxValue($rooms),
            'peak' => $peak,
            'peak_max' => $peakMax,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $assoc
     * @return list<array{label: string, value: int}>
     */
    private static function bucketItems(array $assoc): array
    {
        if ($assoc === []) {
            return [];
        }
        $out = [];
        foreach ($assoc as $label => $v) {
            $n = (int) $v;
            if ($n > 0) {
                $out[] = ['label' => (string) $label, 'value' => $n];
            }
        }
        usort($out, function (array $a, array $b): int {
            if ($a['value'] !== $b['value']) {
                return $b['value'] <=> $a['value'];
            }

            return strcmp($a['label'], $b['label']);
        });

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, value: int}>
     */
    private static function roomItems(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $n = (int) ($row['count'] ?? 0);
            if ($n > 0) {
                $out[] = ['label' => (string) ($row['space_name'] ?? 'Unknown'), 'value' => $n];
            }
        }
        usort($out, function (array $a, array $b): int {
            if ($a['value'] !== $b['value']) {
                return $b['value'] <=> $a['value'];
            }

            return strcmp($a['label'], $b['label']);
        });

        return $out;
    }

    /**
     * Full 0–23 hour series for time-style charts (keys from DB are zero-padded "00".."23").
     *
     * @param  array<string|int, mixed>  $byHour
     * @return list<array{hour: int, label: string, value: int}>
     */
    private static function peak24(array $byHour): array
    {
        $out = [];
        for ($h = 0; $h < 24; $h++) {
            $keyPadded = str_pad((string) $h, 2, '0', STR_PAD_LEFT);
            $raw = $byHour[$keyPadded] ?? $byHour[$h] ?? $byHour[(string) $h] ?? 0;
            $out[] = [
                'hour' => $h,
                'label' => self::peakHourLabel($h),
                'value' => (int) $raw,
            ];
        }

        return $out;
    }

    private static function peakHourLabel(int $hour): string
    {
        return Carbon::createFromTime($hour, 0, 0, config('app.timezone'))->format('g:i A');
    }

    /**
     * @param  list<array{label: string, value: int}>  $items
     * @return list<array{label: string, value: int, pct: float, color: string}>
     */
    private static function stackSegments(array $items): array
    {
        $total = 0;
        foreach ($items as $row) {
            $total += $row['value'];
        }
        if ($total <= 0) {
            return [];
        }
        $stack = [];
        foreach ($items as $i => $row) {
            $stack[] = [
                'label' => $row['label'],
                'value' => $row['value'],
                'pct' => round($row['value'] / $total * 100, 2),
                'color' => self::COLORS[$i % count(self::COLORS)],
            ];
        }

        return $stack;
    }

    /**
     * @param  list<array{label: string, value: int}>  $items
     */
    private static function maxValue(array $items): int
    {
        $m = 0;
        foreach ($items as $row) {
            if ($row['value'] > $m) {
                $m = $row['value'];
            }
        }

        return max(1, $m);
    }
}
