<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rem_reservations')) {
            return;
        }

        Schema::table('rem_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_reservations', 'previous_start_date')) {
                $table->timestamp('previous_start_date')->nullable()->after('end_date');
            }
            if (! Schema::hasColumn('rem_reservations', 'previous_end_date')) {
                $table->timestamp('previous_end_date')->nullable()->after('previous_start_date');
            }
            if (! Schema::hasColumn('rem_reservations', 'rescheduled_at')) {
                $table->timestamp('rescheduled_at')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('rem_reservations', 'reschedule_count')) {
                $table->unsignedInteger('reschedule_count')->default(0)->after('rescheduled_at');
            }
            if (! Schema::hasColumn('rem_reservations', 'cancellation_source')) {
                $table->string('cancellation_source', 40)->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rem_reservations')) {
            return;
        }

        Schema::table('rem_reservations', function (Blueprint $table) {
            $columns = collect([
                'previous_start_date',
                'previous_end_date',
                'rescheduled_at',
                'reschedule_count',
                'cancellation_source',
            ])->filter(fn (string $column) => Schema::hasColumn('rem_reservations', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
