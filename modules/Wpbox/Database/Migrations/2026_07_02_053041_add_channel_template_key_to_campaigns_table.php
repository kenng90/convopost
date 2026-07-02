<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_campaings')) {
            return;
        }

        Schema::table('wa_campaings', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_campaings', 'channel_template_key')) {
                $table->string('channel_template_key', 40)->nullable()->after('template_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_campaings')) {
            return;
        }

        Schema::table('wa_campaings', function (Blueprint $table) {
            if (Schema::hasColumn('wa_campaings', 'channel_template_key')) {
                $table->dropColumn('channel_template_key');
            }
        });
    }
};
