<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (! Schema::hasTable('wa_campaings') || Schema::hasColumn('wa_campaings', 'broadcast_type')) {
            return;
        }

        Schema::table('wa_campaings', function (Blueprint $table) {
            $table->string('broadcast_type')->nullable()->after('group_id'); // file, group, quick
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            //
        });
    }
};
