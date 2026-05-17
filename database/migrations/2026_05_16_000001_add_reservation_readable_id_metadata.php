<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('reservation_sequence')->nullable()->after('reservation_number');
            $table->string('reservation_category', 10)->nullable()->after('reservation_sequence');

            $table->index('reservation_category');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['reservation_category']);
            $table->dropColumn(['reservation_sequence', 'reservation_category']);
        });
    }
};
