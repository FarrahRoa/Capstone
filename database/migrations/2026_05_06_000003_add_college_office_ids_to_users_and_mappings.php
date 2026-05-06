<?php

use App\Models\DeanEmailMapping;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'college_id')) {
                $table->foreignId('college_id')->nullable()->constrained('colleges')->restrictOnDelete();
            }
            if (!Schema::hasColumn('users', 'office_id')) {
                $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            }
        });

        Schema::table('dean_email_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('dean_email_mappings', 'college_id')) {
                $table->foreignId('college_id')->nullable()->constrained('colleges')->restrictOnDelete();
            }
            if (!Schema::hasColumn('dean_email_mappings', 'office_id')) {
                $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            }
        });

        // Backfill IDs from existing string affiliation_name + user.college_office.
        // This keeps the migration safe even if some names do not match exactly.
        if (Schema::hasTable('colleges') && Schema::hasTable('offices')) {
            // Backfill dean_email_mappings
            $collegeByName = DB::table('colleges')->pluck('id', 'name');
            $officeByName = DB::table('offices')->pluck('id', 'name');

            DB::table('dean_email_mappings')
                ->whereNull('college_id')
                ->where('affiliation_type', DeanEmailMapping::TYPE_COLLEGE)
                ->orderBy('id')
                ->get()
                ->each(function ($row) use ($collegeByName) {
                    $id = $collegeByName[$row->affiliation_name] ?? null;
                    if ($id) {
                        DB::table('dean_email_mappings')->where('id', $row->id)->update(['college_id' => $id]);
                    }
                });

            DB::table('dean_email_mappings')
                ->whereNull('office_id')
                ->where('affiliation_type', DeanEmailMapping::TYPE_OFFICE_DEPARTMENT)
                ->orderBy('id')
                ->get()
                ->each(function ($row) use ($officeByName) {
                    $id = $officeByName[$row->affiliation_name] ?? null;
                    if ($id) {
                        DB::table('dean_email_mappings')->where('id', $row->id)->update(['office_id' => $id]);
                    }
                });

            // Backfill users
            DB::table('users')
                ->select('id', 'email', 'user_type', 'college_office')
                ->orderBy('id')
                ->get()
                ->each(function ($u) use ($collegeByName, $officeByName) {
                $unit = trim((string) ($u->college_office ?? ''));
                if ($unit === '') return;
                $type = $u->user_type ?? User::getUserTypeFromEmail((string) $u->email);
                if ($type === User::USER_TYPE_STUDENT) {
                    $id = $collegeByName[$unit] ?? null;
                    if ($id) {
                        DB::table('users')->where('id', $u->id)->update(['college_id' => $id]);
                    }
                } else {
                    $id = $officeByName[$unit] ?? null;
                    if ($id) {
                        DB::table('users')->where('id', $u->id)->update(['office_id' => $id]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('dean_email_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('dean_email_mappings', 'college_id')) {
                $table->dropConstrainedForeignId('college_id');
            }
            if (Schema::hasColumn('dean_email_mappings', 'office_id')) {
                $table->dropConstrainedForeignId('office_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'college_id')) {
                $table->dropConstrainedForeignId('college_id');
            }
            if (Schema::hasColumn('users', 'office_id')) {
                $table->dropConstrainedForeignId('office_id');
            }
        });
    }
};

