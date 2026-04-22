<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dean_email_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('affiliation_type', 32); // college | office_department
            $table->string('affiliation_name', 255);
            $table->string('approver_name', 255)->nullable();
            $table->string('approver_email', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['affiliation_type', 'affiliation_name'], 'dean_mapping_unique_affiliation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dean_email_mappings');
    }
};

