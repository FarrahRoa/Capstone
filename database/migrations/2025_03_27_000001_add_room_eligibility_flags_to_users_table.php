<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('med_confab_eligible')->default(false)->after('year_level');
            $table->boolean('boardroom_eligible')->default(false)->after('med_confab_eligible');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['med_confab_eligible', 'boardroom_eligible']);
        });
    }
};
