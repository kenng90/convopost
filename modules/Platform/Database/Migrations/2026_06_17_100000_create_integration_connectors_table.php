<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('integration_connectors')) {
            return;
        }

        Schema::create('integration_connectors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('provider', 64);
            $table->string('status', 32)->default('disconnected');
            $table->json('credentials')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'provider']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connectors');
    }
};
