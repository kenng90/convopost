<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('phone_number');
            $table->string('twilio_sid')->nullable();
            $table->string('friendly_name')->nullable();
            $table->unsignedBigInteger('voice_flow_id')->nullable();
            $table->json('catalog_ids')->nullable();
            $table->text('ai_greeting')->nullable();
            $table->json('handoff_phrases')->nullable();
            $table->json('required_field_keys')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_phone_numbers');
    }
};
