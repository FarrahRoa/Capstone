<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('event_title', 255)->nullable()->after('purpose');
            $table->text('event_description')->nullable()->after('event_title');
            $table->unsignedSmallInteger('participant_count')->nullable()->after('event_description');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['event_title', 'event_description', 'participant_count']);
        });
    }
};

