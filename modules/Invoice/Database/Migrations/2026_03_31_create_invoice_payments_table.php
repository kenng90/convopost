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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->string('payment_method')->default('mpesa'); // mpesa, stripe, paypal, etc.
            $table->string('mpesa_checkout_request_id')->nullable()->unique();
            $table->string('mpesa_merchant_request_id')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'success', 'failed', 'cancelled'])->default('pending');
            $table->text('result_description')->nullable();
            $table->json('response_data')->nullable(); // Store full Safaricom response
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('payment_method');
            $table->index('status');
            $table->index('mpesa_checkout_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
