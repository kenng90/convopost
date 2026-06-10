<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voice_phone_numbers')) {
            Schema::table('voice_phone_numbers', function (Blueprint $table) {
                if (! Schema::hasColumn('voice_phone_numbers', 'provider')) {
                    $table->string('provider', 16)->default('telnyx')->after('company_id');
                }
            });
        }

        if (Schema::hasTable('voice_calls')) {
            Schema::table('voice_calls', function (Blueprint $table) {
                if (! Schema::hasColumn('voice_calls', 'provider')) {
                    $table->string('provider', 16)->default('telnyx')->after('company_id');
                }
                if (! Schema::hasColumn('voice_calls', 'provider_call_id')) {
                    $table->string('provider_call_id', 64)->nullable()->after('twilio_call_sid');
                    $table->index('provider_call_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('voice_calls', function (Blueprint $table) {
            if (Schema::hasColumn('voice_calls', 'provider_call_id')) {
                $table->dropIndex(['provider_call_id']);
                $table->dropColumn('provider_call_id');
            }
            if (Schema::hasColumn('voice_calls', 'provider')) {
                $table->dropColumn('provider');
            }
        });

        Schema::table('voice_phone_numbers', function (Blueprint $table) {
            if (Schema::hasColumn('voice_phone_numbers', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }
};
