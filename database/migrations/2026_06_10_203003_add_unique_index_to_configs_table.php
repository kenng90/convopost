<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateGroups = DB::table('configs')
            ->select('key', 'model_type', 'model_id', DB::raw('MAX(id) as keep_id'))
            ->groupBy('key', 'model_type', 'model_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            DB::table('configs')
                ->where('key', $group->key)
                ->where('model_type', $group->model_type)
                ->where('model_id', $group->model_id)
                ->where('id', '!=', $group->keep_id)
                ->delete();
        }

        try {
            Schema::table('configs', function (Blueprint $table) {
                $table->unique(['key', 'model_type', 'model_id'], 'configs_key_model_unique');
            });
        } catch (\Throwable) {
            // Unique index already exists on partially migrated databases.
        }
    }

    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->dropUnique('configs_key_model_unique');
        });
    }
};
