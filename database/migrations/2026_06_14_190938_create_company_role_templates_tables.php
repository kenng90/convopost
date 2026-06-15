<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_role_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'is_system']);
        });

        Schema::create('company_role_template_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_role_template_id')->constrained('company_role_templates')->cascadeOnDelete();
            $table->string('module_alias', 64);
            $table->string('permission', 16)->default('manage');
            $table->timestamps();

            $table->unique(['company_role_template_id', 'module_alias'], 'template_module_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_role_template_modules');
        Schema::dropIfExists('company_role_templates');
    }
};
