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
        if (! Schema::hasTable('whatsapp_flows') || Schema::hasColumn('whatsapp_flows', 'category')) {
            return;
        }

        Schema::table('whatsapp_flows', function (Blueprint $table) {
            $table->string('category')->default('OTHER')->after('name');
        });
    }
    
    public function down(): void
    {
        Schema::table('whatsapp_flows', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
