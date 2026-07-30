<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_flows', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_flows', 'flow_source')) {
                $table->string('flow_source', 32)->default('local')->after('status');
            }
            if (! Schema::hasColumn('whatsapp_flows', 'meta_synced_at')) {
                $table->timestamp('meta_synced_at')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('whatsapp_flows', 'default_cta')) {
                $table->string('default_cta')->nullable()->after('meta_synced_at');
            }
            if (! Schema::hasColumn('whatsapp_flows', 'default_header')) {
                $table->string('default_header')->nullable()->after('default_cta');
            }
            if (! Schema::hasColumn('whatsapp_flows', 'default_footer')) {
                $table->string('default_footer')->nullable()->after('default_header');
            }
            if (! Schema::hasColumn('whatsapp_flows', 'meta_flow_json')) {
                $table->json('meta_flow_json')->nullable()->after('flow_json');
            }
        });

        Schema::table('whatsapp_flow_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_flow_responses', 'abandonment_hours')) {
                $table->unsignedSmallInteger('abandonment_hours')->nullable()->after('flow_token');
            }
            if (! Schema::hasColumn('whatsapp_flow_responses', 'variable_prefix')) {
                $table->string('variable_prefix', 64)->nullable()->after('abandonment_hours');
            }
            if (! Schema::hasColumn('whatsapp_flow_responses', 'send_error')) {
                $table->text('send_error')->nullable()->after('variable_prefix');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_flows', function (Blueprint $table) {
            foreach (['flow_source', 'meta_synced_at', 'default_cta', 'default_header', 'default_footer', 'meta_flow_json'] as $column) {
                if (Schema::hasColumn('whatsapp_flows', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('whatsapp_flow_responses', function (Blueprint $table) {
            foreach (['abandonment_hours', 'variable_prefix', 'send_error'] as $column) {
                if (Schema::hasColumn('whatsapp_flow_responses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
