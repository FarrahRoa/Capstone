<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->uuid('cloud_sync_uuid')->nullable()->after('id');
            $table->string('cloud_sync_origin', 32)->default('primary')->after('cloud_sync_uuid');
            $table->timestamp('cloud_synced_at')->nullable()->after('updated_at');
        });

        foreach (DB::table('reservations')->orderBy('id')->cursor() as $row) {
            if ($row->cloud_sync_uuid !== null && $row->cloud_sync_uuid !== '') {
                continue;
            }
            DB::table('reservations')->where('id', $row->id)->update([
                'cloud_sync_uuid' => (string) Str::uuid(),
            ]);
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->unique('cloud_sync_uuid');
        });

        Schema::create('cloud_sync_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('status', 32)->default('info');
            $table->string('summary', 512)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_sync_events');

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['cloud_sync_uuid']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['cloud_sync_uuid', 'cloud_sync_origin', 'cloud_synced_at']);
        });
    }
};
