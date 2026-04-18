<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Node IDs in the Flowmaker are strings like "whatsapp_flow-0.9328112693653096",
     * not integers. Fix the column type so lookups work correctly.
     */
    public function up(): void
    {
        Schema::table('whatsapp_flow_responses', function (Blueprint $table) {
            $table->string('flow_node_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_flow_responses', function (Blueprint $table) {
            $table->unsignedBigInteger('flow_node_id')->nullable()->change();
        });
    }
};
