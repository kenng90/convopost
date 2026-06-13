<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_flows')) {
            return;
        }

        try {
            Schema::table('whatsapp_flows', function (Blueprint $table) {
                $table->index(['company_id', 'deleted_at', 'name'], 'whatsapp_flows_company_name_idx');
            });
        } catch (\Throwable) {
            // Index already exists on partially migrated databases.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_flows', function (Blueprint $table) {
            $table->dropIndex('whatsapp_flows_company_name_idx');
        });
    }
};
