<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            if (! Schema::hasColumn('flows', 'priority')) {
                $table->integer('priority')->default(0)->after('name');
            }
            if (! Schema::hasColumn('flows', 'exclusive_on_match')) {
                $table->boolean('exclusive_on_match')->default(false)->after('priority');
            }
            if (! Schema::hasColumn('flows', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('exclusive_on_match');
            }
        });
    }

    public function down(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            if (Schema::hasColumn('flows', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('flows', 'exclusive_on_match')) {
                $table->dropColumn('exclusive_on_match');
            }
            if (Schema::hasColumn('flows', 'priority')) {
                $table->dropColumn('priority');
            }
        });
    }
};
