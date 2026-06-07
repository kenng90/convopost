<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan', function (Blueprint $table) {
            $table->unsignedInteger('limit_agents')
                ->default(0)
                ->comment('0 is unlimited')
                ->after('limit_catalog_items');
            $table->unsignedInteger('limit_companies')
                ->default(0)
                ->comment('0 is unlimited')
                ->after('limit_agents');
            $table->unsignedInteger('limit_integrations')
                ->default(0)
                ->comment('0 is unlimited')
                ->after('limit_companies');
        });
    }

    public function down(): void
    {
        Schema::table('plan', function (Blueprint $table) {
            $table->dropColumn(['limit_agents', 'limit_companies', 'limit_integrations']);
        });
    }
};
