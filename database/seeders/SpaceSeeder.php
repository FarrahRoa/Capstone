<?php

namespace Database\Seeders;

use App\Models\Space;
use Illuminate\Database\Seeder;

class SpaceSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure "Lecture Space" exists once (idempotent) without creating duplicates if it already exists
        // under an older slug or naming variant.
        $existingLecture = Space::query()
            ->whereIn('slug', ['lecture-space', 'lecture'])
            ->orWhereRaw('LOWER(name) = ?', ['lecture space'])
            ->first();

        if ($existingLecture) {
            $existingLecture->update([
                'name' => 'Lecture Space',
                'type' => 'lecture',
                'is_active' => true,
                'is_confab_pool' => false,
                // Keep existing slug to avoid unexpected changes; normalize if empty.
                'slug' => $existingLecture->slug ?: 'lecture-space',
            ]);
        } else {
            Space::updateOrCreate(
                ['slug' => 'lecture-space'],
                [
                    'name' => 'Lecture Space',
                    'type' => 'lecture',
                    'capacity' => null,
                    'is_active' => true,
                    'is_confab_pool' => false,
                ]
            );
        }

        $spaces = [
            ['name' => 'AVR', 'slug' => 'avr', 'type' => 'avr'],
            ['name' => 'Lobby', 'slug' => 'lobby', 'type' => 'lobby'],
            ['name' => 'Boardroom', 'slug' => 'boardroom', 'type' => 'boardroom'],
            ['name' => 'Medical Confab 1', 'slug' => 'medical-confab-1', 'type' => 'medical_confab'],
            ['name' => 'Medical Confab 2', 'slug' => 'medical-confab-2', 'type' => 'medical_confab'],
            ['name' => 'Confab 1', 'slug' => 'confab-1', 'type' => 'confab'],
            ['name' => 'Confab 2', 'slug' => 'confab-2', 'type' => 'confab'],
            ['name' => 'Confab 3', 'slug' => 'confab-3', 'type' => 'confab'],
            ['name' => 'Confab 4', 'slug' => 'confab-4', 'type' => 'confab'],
            ['name' => 'Confab 5', 'slug' => 'confab-5', 'type' => 'confab'],
            ['name' => 'Confab 6', 'slug' => 'confab-6', 'type' => 'confab'],
        ];
        foreach ($spaces as $space) {
            Space::updateOrCreate(['slug' => $space['slug']], array_merge($space, ['is_confab_pool' => false]));
        }

        Space::updateOrCreate(
            ['slug' => 'confab-pool'],
            [
                'name' => 'Confab',
                'type' => Space::TYPE_CONFAB,
                'capacity' => 1,
                'is_active' => true,
                'is_confab_pool' => true,
            ]
        );
    }
}
