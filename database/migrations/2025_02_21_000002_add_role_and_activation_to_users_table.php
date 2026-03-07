<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('college_office')->nullable()->after('email');
            $table->string('year_level')->nullable()->after('college_office');
            $table->boolean('is_activated')->default(false)->after('year_level');
            $table->string('otp', 6)->nullable()->after('is_activated');
            $table->timestamp('otp_expires_at')->nullable()->after('otp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'college_office', 'year_level', 'is_activated', 'otp', 'otp_expires_at']);
        });
    }
};
