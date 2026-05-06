<?php

use App\Models\College;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (College::query()->count() === 0) {
            foreach (User::allowedStudentColleges() as $name) {
                $t = trim((string) $name);
                if ($t !== '') {
                    College::query()->firstOrCreate(['name' => $t]);
                }
            }
        }

        if (Office::query()->count() === 0) {
            foreach (User::allowedFacultyOffices() as $name) {
                $t = trim((string) $name);
                if ($t !== '') {
                    Office::query()->firstOrCreate(['name' => $t]);
                }
            }
        }
    }

    public function down(): void
    {
        // No-op: seeded reference data should not be deleted automatically.
    }
};

