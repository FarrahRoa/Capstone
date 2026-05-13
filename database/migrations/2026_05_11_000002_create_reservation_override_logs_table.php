<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_override_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('previous_space_id')->nullable()->constrained('spaces')->nullOnDelete();
            $table->dateTime('previous_start_at');
            $table->dateTime('previous_end_at');
            $table->foreignId('new_space_id')->constrained('spaces');
            $table->dateTime('new_start_at');
            $table->dateTime('new_end_at');
            $table->text('reason');
            $table->json('displaced_reservation_ids')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_override_logs');
    }
};
