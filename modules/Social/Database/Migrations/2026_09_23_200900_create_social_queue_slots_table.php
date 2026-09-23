<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSocialQueueSlotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('social_queue_slots')) {
            return;
        }

        Schema::create('social_queue_slots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unsignedTinyInteger('weekday'); // 0=Sunday … 6=Saturday (Carbon)
            $table->string('time', 5); // HH:MM 24h
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'weekday', 'time'], 'social_queue_slots_company_day_time_uq');
            $table->index(['company_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('social_queue_slots');
    }
}
