<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rem_event_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_occurrence_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedSmallInteger('party_size')->default(1);
            $table->string('status', 20)->default('confirmed');
            $table->string('external_id')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('rem_events')->cascadeOnDelete();
            $table->foreign('event_occurrence_id')->references('id')->on('rem_event_occurrences')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->index(['event_occurrence_id', 'status']);
            $table->index(['contact_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rem_event_registrations');
    }
};
