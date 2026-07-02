<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_segments')) {
            return;
        }

        Schema::create('campaign_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->json('filters')->nullable();
            $table->timestamps();

            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_segments');
    }
};
