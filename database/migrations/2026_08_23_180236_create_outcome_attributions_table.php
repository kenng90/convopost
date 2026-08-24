<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('outcome_attributions')) {
            return;
        }

        Schema::create('outcome_attributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('sku', 64);
            $table->string('event', 80);
            $table->string('source', 64)->nullable();
            $table->string('unique_key', 191);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->unsignedInteger('credits_charged')->default(0);
            $table->timestamp('billed_at')->nullable();
            $table->timestamp('guaranteed_until')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'unique_key']);
            $table->index(['company_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outcome_attributions');
    }
};
