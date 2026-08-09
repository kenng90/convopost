<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'conversation_id')) {
                $table->unsignedBigInteger('conversation_id')->nullable()->after('contact_id');
                $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
            }

            if (! Schema::hasColumn('messages', 'channel')) {
                $table->string('channel', 32)->default('whatsapp')->after('conversation_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'conversation_id')) {
                $table->dropForeign(['conversation_id']);
                $table->dropColumn('conversation_id');
            }

            if (Schema::hasColumn('messages', 'channel')) {
                $table->dropColumn('channel');
            }
        });
    }
};
