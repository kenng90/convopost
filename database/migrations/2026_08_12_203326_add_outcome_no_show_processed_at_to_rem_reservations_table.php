<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rem_reservations')) {
            return;
        }

        Schema::table('rem_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_reservations', 'outcome_no_show_processed_at')) {
                $table->timestamp('outcome_no_show_processed_at')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rem_reservations')) {
            return;
        }

        Schema::table('rem_reservations', function (Blueprint $table) {
            if (Schema::hasColumn('rem_reservations', 'outcome_no_show_processed_at')) {
                $table->dropColumn('outcome_no_show_processed_at');
            }
        });
    }
};
