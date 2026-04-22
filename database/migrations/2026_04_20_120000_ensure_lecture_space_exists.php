<?php

use App\Models\Space;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Prevent duplicates: reuse an existing record if it already exists under any known variant.
            $existing = Space::query()
                ->whereIn('slug', ['lecture-space', 'lecture'])
                ->orWhereRaw('LOWER(name) = ?', ['lecture space'])
                ->first();

            if ($existing) {
                $existing->update([
                    'name' => 'Lecture Space',
                    'type' => 'lecture',
                    'is_active' => true,
                    'is_confab_pool' => false,
                ]);
                return;
            }

            Space::create([
                'name' => 'Lecture Space',
                'slug' => 'lecture-space',
                'type' => 'lecture',
                'capacity' => null,
                'is_active' => true,
                'is_confab_pool' => false,
            ]);
        });
    }

    public function down(): void
    {
        // No-op: do not delete data on rollback.
    }
};

