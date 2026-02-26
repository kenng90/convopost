<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateJourinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journeys', function (Blueprint $table) {
            $table->bigIncrements('id');
            //company
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        //Now create the journey_steps table
        Schema::create('journey_stages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('journey_id');
            $table->foreign('journey_id')->references('id')->on('journeys')->onDelete('cascade');
            $table->string('name');
            $table->unsignedBigInteger('campaign_id');
            $table->timestamps();
        });

        //Stage has many contacts
        Schema::create('journey_stage_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('stage_id');
            $table->foreign('stage_id')->references('id')->on('journey_stages')->onDelete('cascade');
            $table->unsignedBigInteger('contact_id');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
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
        Schema::dropIfExists('journeys');
    }
}
