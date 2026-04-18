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
        Schema::create('whatsapp_meta_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique();
            $table->string('waba_id')->comment('WhatsApp Business Account ID');
            $table->string('business_account_id')->nullable()->comment('Meta Business Account ID');
            $table->string('access_token')->comment('Meta API Access Token');
            $table->string('phone_number_id')->nullable()->comment('Phone Number ID for messaging');
            $table->json('credentials_data')->nullable()->comment('Additional credential data from Meta');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable()->comment('Token expiration date');
            $table->timestamp('last_verified_at')->nullable()->comment('Last successful API verification');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index(['company_id', 'is_active']);
            $table->index(['waba_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_meta_credentials');
    }
};
