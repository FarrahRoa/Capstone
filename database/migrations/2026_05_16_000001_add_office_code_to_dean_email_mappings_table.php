<?php

use App\Models\DeanEmailMapping;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dean_email_mappings', function (Blueprint $table) {
            if (! Schema::hasColumn('dean_email_mappings', 'office_code')) {
                $table->string('office_code', 64)->nullable()->after('affiliation_name');
            }
        });

        if (Schema::hasColumn('dean_email_mappings', 'office_code')) {
            DB::table('dean_email_mappings')
                ->where('affiliation_type', DeanEmailMapping::TYPE_OFFICE_DEPARTMENT)
                ->orderBy('id')
                ->get()
                ->each(function ($row) {
                    $code = strtoupper(trim((string) $row->affiliation_name));
                    if ($code === '') {
                        return;
                    }
                    DB::table('dean_email_mappings')->where('id', $row->id)->update(['office_code' => $code]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('dean_email_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('dean_email_mappings', 'office_code')) {
                $table->dropColumn('office_code');
            }
        });
    }
};
