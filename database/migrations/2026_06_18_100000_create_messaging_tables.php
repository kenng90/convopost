<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('external_account_id')->nullable();
            $table->string('display_name')->default('');
            $table->string('status', 32)->default('connected');
            $table->json('credentials')->nullable();
            $table->string('webhook_token')->nullable();
            $table->json('capabilities')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'channel', 'external_account_id'], 'channel_connections_company_channel_account');
            $table->index(['company_id', 'channel']);
        });

        Schema::create('channel_identities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('external_id');
            $table->string('display_name')->default('');
            $table->string('avatar_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'channel', 'external_id'], 'channel_identities_unique_external');
            $table->index(['contact_id', 'channel']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->unsignedBigInteger('channel_connection_id')->nullable();
            $table->foreign('channel_connection_id')->references('id')->on('channel_connections')->nullOnDelete();
            $table->string('channel', 32);
            $table->string('external_thread_id')->nullable();
            $table->string('external_participant_id');
            $table->string('status', 32)->default('open');
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('last_message')->default('');
            $table->timestampTz('last_reply_at')->nullable();
            $table->timestampTz('last_client_reply_at')->nullable();
            $table->timestampTz('last_support_reply_at')->nullable();
            $table->boolean('is_last_message_by_contact')->default(false);
            $table->boolean('enabled_ai_bot')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['channel_connection_id', 'external_participant_id'],
                'conversations_connection_participant_unique'
            );
            $table->index(['company_id', 'channel', 'last_reply_at']);
            $table->index(['company_id', 'assigned_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('channel_identities');
        Schema::dropIfExists('channel_connections');
    }
};
