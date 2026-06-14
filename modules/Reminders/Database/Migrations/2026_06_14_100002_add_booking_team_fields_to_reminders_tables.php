<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Reminders\Models\AppointmentStaff;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rem_res_sources', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('company_id');
            $table->boolean('is_bookable')->default(true)->after('name');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_bookable');

            $table->foreign('department_id')->references('id')->on('rem_departments')->nullOnDelete();
        });

        Schema::table('rem_source_staff', function (Blueprint $table) {
            $table->unsignedBigInteger('appointment_staff_id')->nullable()->after('source_id');
        });

        $this->migrateSourceStaffToAppointmentStaff();

        Schema::table('rem_source_staff', function (Blueprint $table) {
            $table->foreign('appointment_staff_id')->references('id')->on('rem_appointment_staff')->cascadeOnDelete();
            $table->unique(['source_id', 'appointment_staff_id']);
        });

        Schema::table('rem_source_staff', function (Blueprint $table) {
            if (Schema::hasColumn('rem_source_staff', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropUnique(['source_id', 'user_id']);
                $table->dropColumn('user_id');
            }
        });

        Schema::table('rem_reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('appointment_staff_id')->nullable()->after('staff_user_id');
            $table->string('google_event_id')->nullable()->after('external_id');
            $table->unsignedBigInteger('google_calendar_user_id')->nullable()->after('google_event_id');

            $table->foreign('appointment_staff_id')->references('id')->on('rem_appointment_staff')->nullOnDelete();
            $table->foreign('google_calendar_user_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::table('rem_reservations')
            ->whereNotNull('staff_user_id')
            ->orderBy('id')
            ->each(function ($reservation) {
                $staff = AppointmentStaff::query()
                    ->where('company_id', $reservation->company_id)
                    ->where('user_id', $reservation->staff_user_id)
                    ->first();

                if ($staff) {
                    DB::table('rem_reservations')
                        ->where('id', $reservation->id)
                        ->update(['appointment_staff_id' => $staff->id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('rem_reservations', function (Blueprint $table) {
            $table->dropForeign(['appointment_staff_id']);
            $table->dropColumn(['appointment_staff_id', 'google_event_id', 'google_calendar_user_id']);
        });

        Schema::table('rem_source_staff', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('source_id');
        });

        Schema::table('rem_source_staff', function (Blueprint $table) {
            $table->dropForeign(['appointment_staff_id']);
            $table->dropUnique(['source_id', 'appointment_staff_id']);
            $table->dropColumn('appointment_staff_id');
        });

        Schema::table('rem_res_sources', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'is_bookable', 'sort_order']);
        });

        Schema::dropIfExists('rem_appointment_staff');
        Schema::dropIfExists('rem_departments');
    }

    private function migrateSourceStaffToAppointmentStaff(): void
    {
        if (! Schema::hasColumn('rem_source_staff', 'user_id')) {
            return;
        }

        DB::table('rem_source_staff')->orderBy('id')->chunkById(100, function ($assignments) {
            foreach ($assignments as $assignment) {
                if ($assignment->appointment_staff_id) {
                    continue;
                }

                $user = User::find($assignment->user_id);
                if (! $user) {
                    DB::table('rem_source_staff')->where('id', $assignment->id)->delete();

                    continue;
                }

                $source = DB::table('rem_res_sources')->where('id', $assignment->source_id)->first();
                if (! $source) {
                    DB::table('rem_source_staff')->where('id', $assignment->id)->delete();

                    continue;
                }

                $appointmentStaffId = DB::table('rem_appointment_staff')
                    ->where('company_id', $source->company_id)
                    ->where('user_id', $user->id)
                    ->value('id');

                if (! $appointmentStaffId) {
                    $appointmentStaffId = DB::table('rem_appointment_staff')->insertGetId([
                        'company_id' => $source->company_id,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'whatsapp_phone' => $user->phone ?: null,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('rem_source_staff')
                    ->where('id', $assignment->id)
                    ->update(['appointment_staff_id' => $appointmentStaffId]);
            }
        });
    }
};
