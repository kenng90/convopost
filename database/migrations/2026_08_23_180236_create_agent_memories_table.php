<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_memories')) {
            return;
        }

        Schema::create('agent_memories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('channel', 24)->default('chat');
            $table->text('user_message')->nullable();
            $table->text('reply')->nullable();
            $table->json('tools_used')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'contact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_memories');
    }
};
