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
        Schema::table('whatsapp_flows', function (Blueprint $table) {
            $table->index(['company_id', 'deleted_at', 'updated_at'], 'whatsapp_flows_company_listing_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_flows', function (Blueprint $table) {
            $table->dropIndex('whatsapp_flows_company_listing_idx');
        });
    }
};
