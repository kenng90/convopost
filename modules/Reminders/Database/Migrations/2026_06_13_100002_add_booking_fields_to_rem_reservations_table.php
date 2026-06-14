<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rem_reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_user_id')->nullable()->after('source_id');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('end_date');
            $table->timestamp('cancelled_at')->nullable()->after('external_id');

            $table->foreign('staff_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rem_reservations', function (Blueprint $table) {
            $table->dropForeign(['staff_user_id']);
            $table->dropColumn(['staff_user_id', 'duration_minutes', 'cancelled_at']);
        });
    }
};
