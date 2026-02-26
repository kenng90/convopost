<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRemReservationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rem_reservations', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies');

            //Contact ID, Source ID, Date and Time of start, Date and Time of end, Status, Notes, External ID
            $table->unsignedBigInteger('contact_id');
            $table->foreign('contact_id')->references('id')->on('contacts');

            $table->unsignedBigInteger('source_id');
            $table->foreign('source_id')->references('id')->on('rem_res_sources');

            $table->dateTime('start_date');
            $table->dateTime('end_date');

            $table->integer('status')->default(1)->comment('1-active, 2-inactive');
            $table->text('notes')->nullable();  
            $table->string('external_id')->nullable();

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
        Schema::dropIfExists('rem_reservations');
    }
}
