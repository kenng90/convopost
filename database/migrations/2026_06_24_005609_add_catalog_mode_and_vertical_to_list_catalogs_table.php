<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('list_catalogs')) {
            return;
        }

        Schema::table('list_catalogs', function (Blueprint $table) {
            if (! Schema::hasColumn('list_catalogs', 'catalog_mode')) {
                $table->string('catalog_mode', 32)->default('commerce')->after('description');
            }

            if (! Schema::hasColumn('list_catalogs', 'vertical')) {
                $table->string('vertical', 64)->default('retail')->after('catalog_mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('list_catalogs')) {
            return;
        }

        Schema::table('list_catalogs', function (Blueprint $table) {
            if (Schema::hasColumn('list_catalogs', 'vertical')) {
                $table->dropColumn('vertical');
            }

            if (Schema::hasColumn('list_catalogs', 'catalog_mode')) {
                $table->dropColumn('catalog_mode');
            }
        });
    }
};
