<?php

namespace Database\Seeders;

use App\Models\Space;
use Illuminate\Database\Seeder;

class SpaceSeeder extends Seeder
{
    public function run(): void
    {
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
            Space::updateOrCreate(['slug' => $space['slug']], $space);
        }
    }
}
