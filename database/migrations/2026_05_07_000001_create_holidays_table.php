<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->date('date');
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();

            $table->unique(['date', 'name', 'is_recurring'], 'holidays_unique_date_name_recurring');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};

