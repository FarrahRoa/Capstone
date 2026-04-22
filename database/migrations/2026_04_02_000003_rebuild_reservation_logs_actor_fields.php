<?php

use App\Models\ReservationLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite cannot reliably alter foreign keys / nullability in-place without DBAL.
        // Rebuild the table to move from admin-only actor model to a general actor model.
        Schema::disableForeignKeyConstraints();

        Schema::create('reservation_logs_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 20)->nullable(); // user, admin, system
            $table->string('action', 40);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('reservation_id');
            $table->index(['actor_type', 'actor_user_id']);
        });

        // Backfill from old table:
        // - old admin_id becomes actor_user_id (historically it stored "whoever acted", even when mislabeled)
        // - actor_type derived from action
        if (Schema::hasTable('reservation_logs')) {
            $rows = DB::table('reservation_logs')->get();
            foreach ($rows as $row) {
                $action = (string) $row->action;
                $actorType = match ($action) {
                    ReservationLog::ACTION_APPROVE,
                    ReservationLog::ACTION_REJECT,
                    ReservationLog::ACTION_CANCEL,
                    ReservationLog::ACTION_OVERRIDE => ReservationLog::ACTOR_ADMIN,
                    ReservationLog::ACTION_CREATE => ReservationLog::ACTOR_USER,
                    default => null,
                };

                DB::table('reservation_logs_new')->insert([
                    'id' => $row->id,
                    'reservation_id' => $row->reservation_id,
                    'actor_user_id' => $row->admin_id,
                    'actor_type' => $actorType,
                    'action' => $row->action,
                    'notes' => $row->notes,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            Schema::drop('reservation_logs');
            Schema::rename('reservation_logs_new', 'reservation_logs');
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Best-effort rollback: rebuild back to legacy schema (admin_id required).
        Schema::disableForeignKeyConstraints();

        Schema::create('reservation_logs_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 40);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('reservation_id');
        });

        $rows = DB::table('reservation_logs')->get();
        foreach ($rows as $row) {
            // Legacy table cannot represent system actor; drop those rows on rollback.
            if ($row->actor_user_id === null) {
                continue;
            }
            DB::table('reservation_logs_old')->insert([
                'id' => $row->id,
                'reservation_id' => $row->reservation_id,
                'admin_id' => $row->actor_user_id,
                'action' => $row->action,
                'notes' => $row->notes,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('reservation_logs');
        Schema::rename('reservation_logs_old', 'reservation_logs');

        Schema::enableForeignKeyConstraints();
    }
};

