<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->boolean('is_service_managed')->default(false)->after('status');
        });

        DB::table('reminders')
            ->where('name', 'like', '% — client reminder (%')
            ->update(['is_service_managed' => true]);
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn('is_service_managed');
        });
    }
};
