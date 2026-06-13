<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages') || Schema::hasColumn('messages', 'is_call_brief')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_call_brief')->default(false)->after('is_note');
            $table->json('call_brief_payload')->nullable()->after('is_call_brief');
            $table->unsignedBigInteger('call_id')->nullable()->after('call_brief_payload');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['is_call_brief', 'call_brief_payload', 'call_id']);
        });
    }
};
