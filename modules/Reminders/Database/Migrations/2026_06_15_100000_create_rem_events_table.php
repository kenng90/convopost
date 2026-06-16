<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rem_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('appointment_staff_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('virtual_url')->nullable();
            $table->string('image')->nullable();
            $table->string('timezone')->default('UTC');
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('reminder_before_campaign_id')->nullable();
            $table->unsignedBigInteger('reminder_after_campaign_id')->nullable();
            $table->unsignedSmallInteger('reminder_before_value')->nullable();
            $table->string('reminder_before_unit', 20)->nullable();
            $table->unsignedSmallInteger('reminder_after_value')->nullable();
            $table->string('reminder_after_unit', 20)->nullable();
            $table->unsignedBigInteger('confirmation_campaign_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('rem_departments')->nullOnDelete();
            $table->foreign('appointment_staff_id')->references('id')->on('rem_appointment_staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rem_events');
    }
};
