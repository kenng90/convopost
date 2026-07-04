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
            if (! Schema::hasColumn('rem_res_sources', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rem_res_sources')) {
            return;
        }

        Schema::table('rem_res_sources', function (Blueprint $table) {
            if (Schema::hasColumn('rem_res_sources', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
