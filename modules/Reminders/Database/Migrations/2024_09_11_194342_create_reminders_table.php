<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRemindersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies');

            $table->string('name');


            // Foreign key to the posts / source
            $table->unsignedBigInteger('source_id')->nullable()->default(null); //Null -all,
            $table->foreign('source_id')->references('id')->on('rem_res_sources');

            //Type, 1,2
            $table->integer('type')->default(1)->comment('1-Before, 2-After');

            //Time
            $table->integer('time')->default(0);

            //Time type 1-minutes, 2-hours, 3-days, 4-weeks, 5-months, 6-years
            $table->string('time_type')->default(1)->comment('minutes, hours, days, weeks, months, years');

            //Status 1-active, 2-inactive
            $table->integer('status')->default(1)->comment('1-active, 2-inactive');

            //API Campaign ID
            $table->unsignedBigInteger('campaign_id');
            $table->foreign('campaign_id')->references('id')->on('wa_campaings');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reminders');
    }
}
