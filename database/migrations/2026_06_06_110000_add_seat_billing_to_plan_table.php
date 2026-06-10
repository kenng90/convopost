<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan', function (Blueprint $table) {
            $table->unsignedInteger('included_agent_seats')
                ->default(0)
                ->comment('Agent seats included in base price; extra seats use stripe_agent_seat_price_id')
                ->after('limit_integrations');
            $table->string('stripe_agent_seat_price_id', 191)
                ->nullable()
                ->after('included_agent_seats');
            $table->decimal('agent_seat_price', 8, 2)
                ->default(0)
                ->after('stripe_agent_seat_price_id');
            $table->unsignedInteger('included_companies')
                ->default(0)
                ->comment('Organizations included in base price')
                ->after('agent_seat_price');
            $table->string('stripe_company_seat_price_id', 191)
                ->nullable()
                ->after('included_companies');
            $table->decimal('company_seat_price', 8, 2)
                ->default(0)
                ->after('stripe_company_seat_price_id');
        });
    }

    public function down(): void
    {
        Schema::table('plan', function (Blueprint $table) {
            $table->dropColumn([
                'included_agent_seats',
                'stripe_agent_seat_price_id',
                'agent_seat_price',
                'included_companies',
                'stripe_company_seat_price_id',
                'company_seat_price',
            ]);
        });
    }
};
