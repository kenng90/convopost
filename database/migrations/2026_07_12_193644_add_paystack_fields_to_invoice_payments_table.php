<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_payments', 'gateway_reference')) {
                $table->string('gateway_reference')->nullable()->after('mpesa_receipt_number')->index();
            }
            if (! Schema::hasColumn('invoice_payments', 'gateway_access_code')) {
                $table->string('gateway_access_code')->nullable()->after('gateway_reference');
            }
            if (! Schema::hasColumn('invoice_payments', 'authorization_url')) {
                $table->text('authorization_url')->nullable()->after('gateway_access_code');
            }
            if (! Schema::hasColumn('invoice_payments', 'paid_via')) {
                $table->string('paid_via', 40)->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            foreach (['gateway_reference', 'gateway_access_code', 'authorization_url', 'paid_via'] as $column) {
                if (Schema::hasColumn('invoice_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
