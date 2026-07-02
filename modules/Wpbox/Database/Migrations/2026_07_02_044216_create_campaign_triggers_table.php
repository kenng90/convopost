<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_triggers')) {
            return;
        }

        Schema::create('campaign_triggers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('event_type', 80);
            $table->unsignedBigInteger('campaign_id');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'event_type']);
            $table->index(['campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_triggers');
    }
};
