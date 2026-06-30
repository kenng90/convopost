<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_flow_responses') && ! Schema::hasColumn('whatsapp_flow_responses', 'flow_token')) {
            Schema::table('whatsapp_flow_responses', function (Blueprint $table) {
                $table->string('flow_token')->nullable()->after('contact_name');
                $table->index('flow_token');
            });
        }

        if (Schema::hasTable('whatsapp_flow_responses') && ! Schema::hasColumn('whatsapp_flow_responses', 'webhook_dispatched_at')) {
            Schema::table('whatsapp_flow_responses', function (Blueprint $table) {
                $table->timestamp('webhook_dispatched_at')->nullable()->after('completed_at');
            });
        }

        if (Schema::hasTable('whatsapp_flows')) {
            Schema::table('whatsapp_flows', function (Blueprint $table) {
                if (! Schema::hasColumn('whatsapp_flows', 'webhook_url')) {
                    $table->string('webhook_url', 2048)->nullable()->after('notes');
                }
                if (! Schema::hasColumn('whatsapp_flows', 'webhook_enabled')) {
                    $table->boolean('webhook_enabled')->default(false)->after('webhook_url');
                }
                if (! Schema::hasColumn('whatsapp_flows', 'form_bundle_key')) {
                    $table->string('form_bundle_key')->nullable()->after('webhook_enabled');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_flow_responses') && Schema::hasColumn('whatsapp_flow_responses', 'flow_token')) {
            Schema::table('whatsapp_flow_responses', function (Blueprint $table) {
                $table->dropIndex(['flow_token']);
                $table->dropColumn('flow_token');
            });
        }

        if (Schema::hasTable('whatsapp_flows')) {
            Schema::table('whatsapp_flows', function (Blueprint $table) {
                $columns = ['webhook_url', 'webhook_enabled', 'form_bundle_key'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('whatsapp_flows', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
