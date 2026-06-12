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
        if (Schema::hasTable('whatsapp_flows')) {
            return;
        }

        Schema::create('whatsapp_flows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');

            // Meta / WhatsApp Business fields
            $table->string('waba_id')->nullable()->comment('WhatsApp Business Account ID');
            $table->string('meta_flow_id')->nullable()->comment('Meta Flow ID returned from API');
            $table->timestamp('published_at')->nullable()->comment('When flow was published to Meta');
            $table->json('meta_error')->nullable()->comment('Error details if Meta publication failed');

            // Core flow fields
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('flow_json'); // Form structure: fields, validation, layout
            $table->string('status')->default('draft'); // draft, published, archived
            $table->integer('version')->default(1);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'waba_id']);

            // Constraints
            $table->unique(['company_id', 'name', 'version']);
            $table->unique(['meta_flow_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flows');
    }
};