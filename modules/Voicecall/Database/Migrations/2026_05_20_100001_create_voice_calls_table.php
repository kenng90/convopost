<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('voice_phone_number_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('twilio_call_sid')->nullable()->index();
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->string('direction')->default('inbound');
            $table->string('status')->default('ringing');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('transcript')->nullable();
            $table->json('structured')->nullable();
            $table->boolean('handoff_requested')->default(false);
            $table->string('handoff_reason')->nullable();
            $table->unsignedBigInteger('brief_message_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_calls');
    }
};
