<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages') || Schema::hasColumn('messages', 'provider_message_id')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->string('provider_message_id')->nullable()->after('fb_message_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('messages') || ! Schema::hasColumn('messages', 'provider_message_id')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('provider_message_id');
        });
    }
};
