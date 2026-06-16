<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnhanceJourneysModule extends Migration
{
    public function up(): void
    {
        Schema::table('journey_stages', function (Blueprint $table) {
            if (! Schema::hasColumn('journey_stages', 'order')) {
                $table->unsignedInteger('order')->default(0)->after('name');
            }

            if (! Schema::hasColumn('journey_stages', 'campaign_delay_minutes')) {
                $table->unsignedInteger('campaign_delay_minutes')->default(0)->after('campaign_id');
            }
        });

        Schema::table('journey_stages', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id')->nullable()->change();
        });

        Schema::create('journey_activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unsignedBigInteger('journey_id');
            $table->foreign('journey_id')->references('id')->on('journeys')->onDelete('cascade');
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->foreign('stage_id')->references('id')->on('journey_stages')->onDelete('set null');
            $table->unsignedBigInteger('contact_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('action');
            $table->string('source')->default('manual');
            $table->unsignedBigInteger('from_stage_id')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('campaign_status')->nullable();
            $table->text('meta')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'journey_id']);
            $table->index(['journey_id', 'created_at']);
        });

        Schema::create('journey_group_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('journey_id');
            $table->foreign('journey_id')->references('id')->on('journeys')->onDelete('cascade');
            $table->unsignedBigInteger('stage_id');
            $table->foreign('stage_id')->references('id')->on('journey_stages')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['company_id', 'group_id', 'journey_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_group_rules');
        Schema::dropIfExists('journey_activities');

        Schema::table('journey_stages', function (Blueprint $table) {
            if (Schema::hasColumn('journey_stages', 'campaign_delay_minutes')) {
                $table->dropColumn('campaign_delay_minutes');
            }

            if (Schema::hasColumn('journey_stages', 'order')) {
                $table->dropColumn('order');
            }
        });
    }
}
