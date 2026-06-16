<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::table('roles')->insert([
                'name' => 'org_manager',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Role may already exist.
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'org_manager')->where('guard_name', 'web')->delete();
    }
};
