<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Status 6 = cancelled (documented in Campaign model constants)
        // No schema change required; migration exists for audit trail.
    }

    public function down(): void
    {
        //
    }
};
