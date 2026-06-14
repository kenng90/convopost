<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rem_res_sources', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_duration_minutes')->default(30)->after('name');
            $table->unsignedSmallInteger('buffer_minutes')->default(0)->after('default_duration_minutes');
            $table->json('duration_options')->nullable()->after('buffer_minutes');
            $table->json('working_hours')->nullable()->after('duration_options');
            $table->string('timezone')->default('UTC')->after('working_hours');
            $table->unsignedSmallInteger('min_notice_hours')->default(1)->after('timezone');
            $table->unsignedSmallInteger('max_advance_days')->default(60)->after('min_notice_hours');
        });
    }

    public function down(): void
    {
        Schema::table('rem_res_sources', function (Blueprint $table) {
            $table->dropColumn([
                'default_duration_minutes',
                'buffer_minutes',
                'duration_options',
                'working_hours',
                'timezone',
                'min_notice_hours',
                'max_advance_days',
            ]);
        });
    }
};
