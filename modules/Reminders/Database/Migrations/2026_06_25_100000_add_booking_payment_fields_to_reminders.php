<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rem_res_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_res_sources', 'payment_required')) {
                $table->boolean('payment_required')->default(false)->after('is_bookable');
            }
            if (! Schema::hasColumn('rem_res_sources', 'payment_amount')) {
                $table->decimal('payment_amount', 12, 2)->nullable()->after('payment_required');
            }
            if (! Schema::hasColumn('rem_res_sources', 'payment_currency')) {
                $table->string('payment_currency', 3)->default('KES')->after('payment_amount');
            }
        });

        Schema::table('rem_events', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_events', 'payment_required')) {
                $table->boolean('payment_required')->default(false)->after('is_published');
            }
            if (! Schema::hasColumn('rem_events', 'payment_amount')) {
                $table->decimal('payment_amount', 12, 2)->nullable()->after('payment_required');
            }
            if (! Schema::hasColumn('rem_events', 'payment_currency')) {
                $table->string('payment_currency', 3)->default('KES')->after('payment_amount');
            }
        });

        Schema::table('rem_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_reservations', 'payment_status')) {
                $table->string('payment_status')->nullable()->after('external_id');
            }
            if (! Schema::hasColumn('rem_reservations', 'payment_amount')) {
                $table->decimal('payment_amount', 12, 2)->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('rem_reservations', 'payment_currency')) {
                $table->string('payment_currency', 3)->nullable()->after('payment_amount');
            }
            if (! Schema::hasColumn('rem_reservations', 'invoice_payment_id')) {
                $table->unsignedBigInteger('invoice_payment_id')->nullable()->after('payment_currency');
            }
        });

        Schema::table('rem_event_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('rem_event_registrations', 'payment_status')) {
                $table->string('payment_status')->nullable()->after('external_id');
            }
            if (! Schema::hasColumn('rem_event_registrations', 'payment_amount')) {
                $table->decimal('payment_amount', 12, 2)->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('rem_event_registrations', 'payment_currency')) {
                $table->string('payment_currency', 3)->nullable()->after('payment_amount');
            }
            if (! Schema::hasColumn('rem_event_registrations', 'invoice_payment_id')) {
                $table->unsignedBigInteger('invoice_payment_id')->nullable()->after('payment_currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rem_event_registrations', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('rem_event_registrations', 'invoice_payment_id') ? 'invoice_payment_id' : null,
                Schema::hasColumn('rem_event_registrations', 'payment_currency') ? 'payment_currency' : null,
                Schema::hasColumn('rem_event_registrations', 'payment_amount') ? 'payment_amount' : null,
                Schema::hasColumn('rem_event_registrations', 'payment_status') ? 'payment_status' : null,
            ]);
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('rem_reservations', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('rem_reservations', 'invoice_payment_id') ? 'invoice_payment_id' : null,
                Schema::hasColumn('rem_reservations', 'payment_currency') ? 'payment_currency' : null,
                Schema::hasColumn('rem_reservations', 'payment_amount') ? 'payment_amount' : null,
                Schema::hasColumn('rem_reservations', 'payment_status') ? 'payment_status' : null,
            ]);
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('rem_events', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('rem_events', 'payment_currency') ? 'payment_currency' : null,
                Schema::hasColumn('rem_events', 'payment_amount') ? 'payment_amount' : null,
                Schema::hasColumn('rem_events', 'payment_required') ? 'payment_required' : null,
            ]);
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('rem_res_sources', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('rem_res_sources', 'payment_currency') ? 'payment_currency' : null,
                Schema::hasColumn('rem_res_sources', 'payment_amount') ? 'payment_amount' : null,
                Schema::hasColumn('rem_res_sources', 'payment_required') ? 'payment_required' : null,
            ]);
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
