<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_flow_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('whatsapp_flow_id')->index();
            $table->unsignedBigInteger('flow_id')->nullable()->index();
            $table->unsignedBigInteger('flow_node_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('contact_phone')->nullable();
            $table->string('contact_name')->nullable();
            $table->json('responses')->nullable();
            $table->enum('status', ['pending', 'completed', 'abandoned', 'failed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('whatsapp_flow_id')->references('id')->on('whatsapp_flows')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_responses');
    }
};
