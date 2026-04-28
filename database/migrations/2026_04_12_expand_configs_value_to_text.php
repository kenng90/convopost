<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The configs.value column was originally a string (VARCHAR 255).
     * RSA private key PEMs are ~1700 characters and were being silently truncated,
     * causing "DECODER routines::unsupported" when the truncated key was loaded.
     * Change to TEXT so any config value (keys, long tokens, etc.) is stored in full.
     */
    public function up(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->string('value')->nullable()->change();
        });
    }
};
