<?php

namespace App\Support;

use App\Models\Space;

/**
 * User-facing Confab room rows for reservation guidelines (numbered rooms only, not the assignment pool).
 */
final class ConfabGuidelinesComparison
{
    /**
     * @return list<array{name: string, capacity: int|null, guideline_details: array<string, mixed>|null}>
     */
    public static function physicalConfabRoomsPayload(): array
    {
        return Space::query()
            ->where('is_active', true)
            ->where('type', Space::TYPE_CONFAB)
            ->where('is_confab_pool', false)
            ->orderBy('name')
            ->get(['name', 'capacity', 'guideline_details'])
            ->map(fn (Space $s) => [
                'name' => (string) $s->name,
                'capacity' => $s->capacity,
                'guideline_details' => SpaceGuidelineDetails::forApi($s->guideline_details),
            ])
            ->values()
            ->all();
    }
}
