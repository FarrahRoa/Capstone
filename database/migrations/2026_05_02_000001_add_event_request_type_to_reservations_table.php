<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('event_request_type', 32)->nullable()->after('participant_count');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('event_request_type');
        });
    }
};
