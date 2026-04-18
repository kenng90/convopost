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
        Schema::create('list_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
            $table->string('name'); // "Products", "Services", etc
            $table->text('description')->nullable();
            $table->integer('version')->default(1);
            $table->foreignId('parent_id')->nullable(); // Link to v1 for versioning
            $table->json('items'); // Array of items with id, title, description
            $table->json('columns'); // Which columns exist in data
            $table->enum('source', ['manual', 'excel', 'api'])->default('manual');
            $table->string('original_file_name')->nullable(); // For audit/reference
            $table->json('metadata')->nullable(); // Additional info (api_url, api_params, etc)
            $table->timestamps();

            // Ensure unique catalog name per company per version
            $table->unique(['company_id', 'name', 'version']);
            $table->index(['company_id', 'source']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('list_catalogs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_catalogs');
    }
};
