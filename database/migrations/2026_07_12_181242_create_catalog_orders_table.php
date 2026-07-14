<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_orders')) {
            Schema::create('catalog_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('catalog_id')->nullable()->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                $table->unsignedBigInteger('flow_id')->nullable()->index();
                $table->string('order_number')->index();
                $table->string('public_uuid', 36)->unique();
                $table->string('status', 40)->default('pending')->index();
                $table->string('checkout_channel', 40)->default('whatsapp');
                $table->string('customer_name')->nullable();
                $table->string('customer_phone', 40)->nullable()->index();
                $table->text('delivery_address')->nullable();
                $table->text('notes')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('KES');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'order_number']);
            });
        }

        if (! Schema::hasTable('catalog_order_items')) {
            Schema::create('catalog_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('catalog_order_id')->index();
                $table->string('item_id')->nullable();
                $table->string('title');
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('line_total', 12, 2)->default(0);
                $table->string('variant')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('catalog_order_id')
                    ->references('id')
                    ->on('catalog_orders')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('catalog_cart_sessions')) {
            Schema::create('catalog_cart_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('catalog_id')->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->string('visitor_key', 64)->index();
                $table->json('items')->nullable();
                $table->string('customer_phone', 40)->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->timestamp('abandoned_at')->nullable()->index();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();

                $table->unique(['catalog_id', 'visitor_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_order_items');
        Schema::dropIfExists('catalog_orders');
        Schema::dropIfExists('catalog_cart_sessions');
    }
};
