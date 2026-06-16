<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rem_departments', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_departments', 'working_hours')) {
                $table->json('working_hours')->nullable()->after('description');
            }
            if (! Schema::hasColumn('rem_departments', 'timezone')) {
                $table->string('timezone')->nullable()->after('working_hours');
            }
        });

        Schema::table('rem_res_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_res_sources', 'staff_assignment_mode')) {
                $table->string('staff_assignment_mode')->default('customer_choice')->after('max_advance_days');
            }
        });

        Schema::table('rem_source_staff', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_source_staff', 'last_assigned_at')) {
                $table->timestamp('last_assigned_at')->nullable()->after('is_active');
            }
        });

        if (! Schema::hasTable('rem_booking_closures')) {
            Schema::create('rem_booking_closures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('label')->nullable();
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('department_id')->references('id')->on('rem_departments')->nullOnDelete();
                $table->index(['company_id', 'department_id', 'starts_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rem_booking_closures');

        Schema::table('rem_source_staff', function (Blueprint $table) {
            if (Schema::hasColumn('rem_source_staff', 'last_assigned_at')) {
                $table->dropColumn('last_assigned_at');
            }
        });

        Schema::table('rem_res_sources', function (Blueprint $table) {
            if (Schema::hasColumn('rem_res_sources', 'staff_assignment_mode')) {
                $table->dropColumn('staff_assignment_mode');
            }
        });

        Schema::table('rem_departments', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('rem_departments', 'timezone') ? 'timezone' : null,
                Schema::hasColumn('rem_departments', 'working_hours') ? 'working_hours' : null,
            ]);
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
