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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('catalog_id')->nullable()->constrained('list_catalogs')->onDelete('set null');
            $table->string('invoice_number')->unique();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('KES');
            $table->enum('status', ['draft', 'sent', 'pending', 'paid', 'cancelled', 'failed'])->default('draft');
            $table->text('description')->nullable();
            $table->json('items')->nullable(); // Stores order items from catalog
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('notes')->nullable(); // Additional notes/metadata
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('catalog_id');
            $table->index('customer_phone');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
