<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rem_res_sources')) {
            return;
        }

        Schema::table('rem_res_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_res_sources', 'location')) {
                $table->string('location')->nullable()->after('timezone');
            }

            if (! Schema::hasColumn('rem_res_sources', 'confirmation_campaign_id')) {
                $table->unsignedBigInteger('confirmation_campaign_id')->nullable()->after('location');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rem_res_sources')) {
            return;
        }

        Schema::table('rem_res_sources', function (Blueprint $table) {
            if (Schema::hasColumn('rem_res_sources', 'confirmation_campaign_id')) {
                $table->dropColumn('confirmation_campaign_id');
            }

            if (Schema::hasColumn('rem_res_sources', 'location')) {
                $table->dropColumn('location');
            }
        });
    }
};
