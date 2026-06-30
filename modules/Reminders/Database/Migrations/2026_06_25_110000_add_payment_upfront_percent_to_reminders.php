<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rem_res_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_res_sources', 'payment_upfront_percent')) {
                $table->unsignedTinyInteger('payment_upfront_percent')->nullable()->after('payment_amount');
            }
        });

        Schema::table('rem_events', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_events', 'payment_upfront_percent')) {
                $table->unsignedTinyInteger('payment_upfront_percent')->nullable()->after('payment_amount');
            }
        });

        Schema::table('rem_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_reservations', 'payment_total_amount')) {
                $table->decimal('payment_total_amount', 12, 2)->nullable()->after('payment_amount');
            }
        });

        Schema::table('rem_event_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_event_registrations', 'payment_total_amount')) {
                $table->decimal('payment_total_amount', 12, 2)->nullable()->after('payment_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rem_event_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('rem_event_registrations', 'payment_total_amount')) {
                $table->dropColumn('payment_total_amount');
            }
        });

        Schema::table('rem_reservations', function (Blueprint $table) {
            if (Schema::hasColumn('rem_reservations', 'payment_total_amount')) {
                $table->dropColumn('payment_total_amount');
            }
        });

        Schema::table('rem_events', function (Blueprint $table) {
            if (Schema::hasColumn('rem_events', 'payment_upfront_percent')) {
                $table->dropColumn('payment_upfront_percent');
            }
        });

        Schema::table('rem_res_sources', function (Blueprint $table) {
            if (Schema::hasColumn('rem_res_sources', 'payment_upfront_percent')) {
                $table->dropColumn('payment_upfront_percent');
            }
        });
    }
};
