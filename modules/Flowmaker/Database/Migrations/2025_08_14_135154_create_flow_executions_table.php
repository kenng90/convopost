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
        Schema::create('flow_executions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flow_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('node_id')->nullable(); // To track which counter node was executed
            $table->timestamp('executed_at');
            $table->timestamps();
            
            // Add indexes for performance
            $table->index(['flow_id', 'contact_id', 'executed_at']);
            $table->index(['flow_id', 'contact_id', 'node_id', 'executed_at']);
            
            // Foreign key constraints (if the tables exist)
            // $table->foreign('flow_id')->references('id')->on('flows')->onDelete('cascade');
            // $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flow_executions');
    }
};
