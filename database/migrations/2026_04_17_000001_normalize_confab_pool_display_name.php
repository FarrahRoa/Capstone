<?php

use App\Models\Space;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Space::query()
            ->where('slug', 'confab-pool')
            ->update(['name' => 'Confab']);

        Space::query()
            ->where('name', 'Confab (room assigned at approval)')
            ->update(['name' => 'Confab']);
    }

    public function down(): void
    {
        Space::query()
            ->where('slug', 'confab-pool')
            ->update(['name' => 'Confab (room assigned at approval)']);
    }
};
