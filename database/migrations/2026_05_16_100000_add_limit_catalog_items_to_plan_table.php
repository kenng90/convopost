<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan', function (Blueprint $table) {
            $table->unsignedInteger('limit_catalog_items')
                ->default(0)
                ->comment('0 is unlimited per plan period')
                ->after('limit_views');
        });
    }

    public function down(): void
    {
        Schema::table('plan', function (Blueprint $table) {
            $table->dropColumn('limit_catalog_items');
        });
    }
};
