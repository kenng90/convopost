<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rem_reservations')) {
            Schema::table('rem_reservations', function (Blueprint $table) {
                if (! Schema::hasColumn('rem_reservations', 'booking_source')) {
                    $table->string('booking_source', 32)->nullable()->after('flow_node_id');
                }
                if (! Schema::hasColumn('rem_reservations', 'voice_call_id')) {
                    $table->unsignedBigInteger('voice_call_id')->nullable()->after('booking_source');
                }
                if (! Schema::hasColumn('rem_reservations', 'payment_hold_expires_at')) {
                    $table->timestamp('payment_hold_expires_at')->nullable()->after('payment_amount');
                }
            });
        }

        if (Schema::hasTable('rem_event_registrations')) {
            Schema::table('rem_event_registrations', function (Blueprint $table) {
                if (! Schema::hasColumn('rem_event_registrations', 'booking_source')) {
                    $table->string('booking_source', 32)->nullable()->after('flow_node_id');
                }
                if (! Schema::hasColumn('rem_event_registrations', 'voice_call_id')) {
                    $table->unsignedBigInteger('voice_call_id')->nullable()->after('booking_source');
                }
                if (! Schema::hasColumn('rem_event_registrations', 'payment_hold_expires_at')) {
                    $table->timestamp('payment_hold_expires_at')->nullable()->after('payment_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rem_reservations')) {
            Schema::table('rem_reservations', function (Blueprint $table) {
                foreach (['booking_source', 'voice_call_id', 'payment_hold_expires_at'] as $column) {
                    if (Schema::hasColumn('rem_reservations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('rem_event_registrations')) {
            Schema::table('rem_event_registrations', function (Blueprint $table) {
                foreach (['booking_source', 'voice_call_id', 'payment_hold_expires_at'] as $column) {
                    if (Schema::hasColumn('rem_event_registrations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
