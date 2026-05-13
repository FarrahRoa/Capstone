<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->text('override_reason')->nullable()->after('rejected_reason');
            $table->foreignId('overridden_by')->nullable()->after('override_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable()->after('overridden_by');
            $table->foreignId('override_previous_space_id')->nullable()->after('overridden_at')->constrained('spaces')->nullOnDelete();
            $table->dateTime('override_previous_start_at')->nullable()->after('override_previous_space_id');
            $table->dateTime('override_previous_end_at')->nullable()->after('override_previous_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['overridden_by']);
            $table->dropForeign(['override_previous_space_id']);
            $table->dropColumn([
                'override_reason',
                'overridden_by',
                'overridden_at',
                'override_previous_space_id',
                'override_previous_start_at',
                'override_previous_end_at',
            ]);
        });
    }
};
