<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->string('handled_by_type')->default('human')->after('status');
            $table->unsignedInteger('duration_seconds')->nullable()->after('handled_by_type');
            $table->string('intent')->nullable()->after('duration_seconds');
            $table->text('summary')->nullable()->after('intent');
            $table->json('structured')->nullable()->after('summary');
            $table->longText('transcript')->nullable()->after('structured');
            $table->boolean('handoff_requested')->default(false)->after('transcript');
            $table->string('handoff_reason')->nullable()->after('handoff_requested');
            $table->unsignedBigInteger('brief_message_id')->nullable()->after('handoff_reason');
            $table->string('ai_session_id')->nullable()->after('brief_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn([
                'handled_by_type',
                'duration_seconds',
                'intent',
                'summary',
                'structured',
                'transcript',
                'handoff_requested',
                'handoff_reason',
                'brief_message_id',
                'ai_session_id',
            ]);
        });
    }
};
