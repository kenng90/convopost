<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_events')) {
            Schema::create('platform_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('event', 80);
                $table->json('payload')->nullable();
                $table->string('signature', 128)->nullable();
                $table->string('status', 24)->default('pending');
                $table->timestamp('delivered_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'event']);
            });
        }

        if (! Schema::hasTable('platform_audit_logs')) {
            Schema::create('platform_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 80);
                $table->string('subject_type', 120)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'action']);
            });
        }

        if (! Schema::hasTable('consent_records')) {
            Schema::create('consent_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->string('channel', 32)->default('whatsapp');
                $table->string('type', 32)->default('opt_in');
                $table->string('source', 64)->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->index(['company_id', 'contact_id']);
            });
        }

        if (! Schema::hasTable('conversation_workspaces')) {
            Schema::create('conversation_workspaces', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('contact_id')->unique();
                $table->timestamp('sla_started_at')->nullable();
                $table->timestamp('sla_due_at')->nullable();
                $table->timestamp('sla_breached_at')->nullable();
                $table->unsignedInteger('sla_minutes')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->unsignedTinyInteger('csat_score')->nullable();
                $table->string('csat_comment', 500)->nullable();
                $table->timestamp('csat_requested_at')->nullable();
                $table->timestamp('csat_submitted_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'sla_due_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_workspaces');
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_events');
    }
};
