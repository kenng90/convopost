<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_membership_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_membership_id')->constrained('company_memberships')->cascadeOnDelete();
            $table->string('module_alias', 64);
            $table->string('permission', 16)->default('manage');
            $table->timestamps();

            $table->unique(['company_membership_id', 'module_alias'], 'membership_module_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_membership_modules');
    }
};
