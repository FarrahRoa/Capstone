<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spaces') || Schema::hasColumn('spaces', 'image_path')) {
            return;
        }

        Schema::table('spaces', function (Blueprint $table) {
            $table->string('image_path', 2048)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spaces') || ! Schema::hasColumn('spaces', 'image_path')) {
            return;
        }

        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
