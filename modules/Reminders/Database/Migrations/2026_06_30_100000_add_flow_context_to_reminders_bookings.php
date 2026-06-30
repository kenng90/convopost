<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rem_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_reservations', 'flow_id')) {
                $table->unsignedBigInteger('flow_id')->nullable()->after('external_id');
            }
            if (! Schema::hasColumn('rem_reservations', 'flow_node_id')) {
                $table->string('flow_node_id', 64)->nullable()->after('flow_id');
            }
        });

        Schema::table('rem_event_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_event_registrations', 'flow_id')) {
                $table->unsignedBigInteger('flow_id')->nullable()->after('external_id');
            }
            if (! Schema::hasColumn('rem_event_registrations', 'flow_node_id')) {
                $table->string('flow_node_id', 64)->nullable()->after('flow_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rem_reservations', function (Blueprint $table) {
            $table->dropColumn(['flow_id', 'flow_node_id']);
        });

        Schema::table('rem_event_registrations', function (Blueprint $table) {
            $table->dropColumn(['flow_id', 'flow_node_id']);
        });
    }
};
