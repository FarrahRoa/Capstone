<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spaces') || Schema::hasColumn('spaces', 'guideline_details')) {
            return;
        }

        Schema::table('spaces', function (Blueprint $table) {
            $table->json('guideline_details')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spaces') || ! Schema::hasColumn('spaces', 'guideline_details')) {
            return;
        }

        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn('guideline_details');
        });
    }
};
