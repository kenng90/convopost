<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rem_appointment_staff_department')) {
            Schema::create('rem_appointment_staff_department', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('appointment_staff_id');
                $table->unsignedBigInteger('department_id');
                $table->timestamps();

                $table->foreign('appointment_staff_id')
                    ->references('id')
                    ->on('rem_appointment_staff')
                    ->cascadeOnDelete();
                $table->foreign('department_id')
                    ->references('id')
                    ->on('rem_departments')
                    ->cascadeOnDelete();
                $table->unique(['appointment_staff_id', 'department_id'], 'rem_staff_dept_unique');
            });
        }

        if (! Schema::hasTable('rem_appointment_staff') || ! Schema::hasTable('rem_appointment_staff_department')) {
            return;
        }

        $rows = DB::table('rem_appointment_staff')
            ->whereNotNull('department_id')
            ->select('id', 'department_id')
            ->get();

        foreach ($rows as $row) {
            $exists = DB::table('rem_appointment_staff_department')
                ->where('appointment_staff_id', $row->id)
                ->where('department_id', $row->department_id)
                ->exists();

            if (! $exists) {
                DB::table('rem_appointment_staff_department')->insert([
                    'appointment_staff_id' => $row->id,
                    'department_id' => $row->department_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rem_appointment_staff_department');
    }
};
