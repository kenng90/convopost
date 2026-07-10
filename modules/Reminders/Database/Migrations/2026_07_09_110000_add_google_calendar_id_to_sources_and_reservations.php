<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rem_res_sources') && ! Schema::hasColumn('rem_res_sources', 'google_calendar_id')) {
            Schema::table('rem_res_sources', function (Blueprint $table) {
                $table->string('google_calendar_id')->nullable()->after('location');
            });
        }

        if (Schema::hasTable('rem_reservations') && ! Schema::hasColumn('rem_reservations', 'google_calendar_id')) {
            Schema::table('rem_reservations', function (Blueprint $table) {
                $table->string('google_calendar_id')->nullable()->after('google_calendar_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rem_res_sources') && Schema::hasColumn('rem_res_sources', 'google_calendar_id')) {
            Schema::table('rem_res_sources', function (Blueprint $table) {
                $table->dropColumn('google_calendar_id');
            });
        }

        if (Schema::hasTable('rem_reservations') && Schema::hasColumn('rem_reservations', 'google_calendar_id')) {
            Schema::table('rem_reservations', function (Blueprint $table) {
                $table->dropColumn('google_calendar_id');
            });
        }
    }
};
