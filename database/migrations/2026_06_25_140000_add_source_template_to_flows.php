<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flows') && ! Schema::hasColumn('flows', 'source_template')) {
            Schema::table('flows', function (Blueprint $table) {
                $table->string('source_template')->nullable()->after('has_unpublished_changes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('flows') && Schema::hasColumn('flows', 'source_template')) {
            Schema::table('flows', function (Blueprint $table) {
                $table->dropColumn('source_template');
            });
        }
    }
};
