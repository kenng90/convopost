<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_commerce_webhook_events')) {
            return;
        }

        Schema::create('store_commerce_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('provider', 32);
            $table->string('event_type', 64);
            $table->string('external_id', 128)->nullable();
            $table->string('dedupe_key', 191)->unique();
            $table->json('payload_meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_commerce_webhook_events');
    }
};
