<?php

use App\Models\Space;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->boolean('is_confab_pool')->default(false)->after('is_active');
        });

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

    public function down(): void
    {
        Space::where('slug', 'confab-pool')->delete();
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn('is_confab_pool');
        });
    }
};
