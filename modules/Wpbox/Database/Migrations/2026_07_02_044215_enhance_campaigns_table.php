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
            if (! Schema::hasColumn('wa_campaings', 'status')) {
                $table->string('status', 40)->default('scheduled')->after('is_active');
            }
            if (! Schema::hasColumn('wa_campaings', 'channel')) {
                $table->string('channel', 20)->default('whatsapp')->after('status');
            }
            if (! Schema::hasColumn('wa_campaings', 'segment_id')) {
                $table->unsignedBigInteger('segment_id')->nullable()->after('group_id');
            }
            if (! Schema::hasColumn('wa_campaings', 'recurrence_rule')) {
                $table->json('recurrence_rule')->nullable()->after('timestamp_for_delivery');
            }
            if (! Schema::hasColumn('wa_campaings', 'recurrence_next_at')) {
                $table->timestamp('recurrence_next_at')->nullable()->after('recurrence_rule');
            }
            if (! Schema::hasColumn('wa_campaings', 'ab_variant')) {
                $table->string('ab_variant', 1)->nullable()->after('recurrence_next_at');
            }
            if (! Schema::hasColumn('wa_campaings', 'ab_parent_id')) {
                $table->unsignedBigInteger('ab_parent_id')->nullable()->after('ab_variant');
            }
            if (! Schema::hasColumn('wa_campaings', 'cloned_from_id')) {
                $table->unsignedBigInteger('cloned_from_id')->nullable()->after('ab_parent_id');
            }
            if (! Schema::hasColumn('wa_campaings', 'timezone_mode')) {
                $table->string('timezone_mode', 20)->default('contact')->after('cloned_from_id');
            }
            if (! Schema::hasColumn('wa_campaings', 'launched_at')) {
                $table->timestamp('launched_at')->nullable()->after('timezone_mode');
            }
            if (! Schema::hasColumn('wa_campaings', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('launched_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_campaings')) {
            return;
        }

        Schema::table('wa_campaings', function (Blueprint $table) {
            $columns = [
                'status', 'channel', 'segment_id', 'recurrence_rule', 'recurrence_next_at',
                'ab_variant', 'ab_parent_id', 'cloned_from_id', 'timezone_mode',
                'launched_at', 'completed_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('wa_campaings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
