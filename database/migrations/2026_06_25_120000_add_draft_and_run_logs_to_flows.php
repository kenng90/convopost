<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flows')) {
            Schema::table('flows', function (Blueprint $table) {
                if (! Schema::hasColumn('flows', 'draft_flow_data')) {
                    $table->text('draft_flow_data')->nullable()->after('flow_data');
                }
                if (! Schema::hasColumn('flows', 'has_unpublished_changes')) {
                    $table->boolean('has_unpublished_changes')->default(false)->after('draft_flow_data');
                }
            });
        }

        if (! Schema::hasTable('flow_run_logs')) {
            Schema::create('flow_run_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id');
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->string('node_id')->nullable();
                $table->string('event', 64);
                $table->text('detail')->nullable();
                $table->timestamps();

                $table->index(['flow_id', 'created_at']);
                $table->index(['contact_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_run_logs');

        if (Schema::hasTable('flows')) {
            Schema::table('flows', function (Blueprint $table) {
                if (Schema::hasColumn('flows', 'has_unpublished_changes')) {
                    $table->dropColumn('has_unpublished_changes');
                }
                if (Schema::hasColumn('flows', 'draft_flow_data')) {
                    $table->dropColumn('draft_flow_data');
                }
            });
        }
    }
};
