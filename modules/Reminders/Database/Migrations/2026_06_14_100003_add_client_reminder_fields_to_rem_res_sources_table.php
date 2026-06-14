<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rem_res_sources', function (Blueprint $table) {
            $table->unsignedBigInteger('reminder_before_campaign_id')->nullable()->after('max_advance_days');
            $table->unsignedBigInteger('reminder_after_campaign_id')->nullable()->after('reminder_before_campaign_id');
            $table->unsignedSmallInteger('reminder_before_value')->nullable()->after('reminder_after_campaign_id');
            $table->string('reminder_before_unit', 20)->nullable()->after('reminder_before_value');
            $table->unsignedSmallInteger('reminder_after_value')->nullable()->after('reminder_before_unit');
            $table->string('reminder_after_unit', 20)->nullable()->after('reminder_after_value');
        });
    }

    public function down(): void
    {
        Schema::table('rem_res_sources', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_before_campaign_id',
                'reminder_after_campaign_id',
                'reminder_before_value',
                'reminder_before_unit',
                'reminder_after_value',
                'reminder_after_unit',
            ]);
        });
    }
};
