<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('admin_invite_token_hash')->nullable()->after('password');
            $table->timestamp('admin_invite_expires_at')->nullable()->after('admin_invite_token_hash');
            $table->timestamp('admin_invited_at')->nullable()->after('admin_invite_expires_at');
            $table->timestamp('admin_password_set_at')->nullable()->after('admin_invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'admin_invite_token_hash',
                'admin_invite_expires_at',
                'admin_invited_at',
                'admin_password_set_at',
            ]);
        });
    }
};

