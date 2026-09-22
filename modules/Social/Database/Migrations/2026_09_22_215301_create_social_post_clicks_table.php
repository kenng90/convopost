<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSocialPostClicksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('social_post_clicks')) {
            return;
        }

        Schema::create('social_post_clicks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unsignedBigInteger('social_offer_link_id');
            $table->foreign('social_offer_link_id')->references('id')->on('social_offer_links')->onDelete('cascade');
            $table->unsignedBigInteger('social_post_id')->nullable();
            $table->foreign('social_post_id')->references('id')->on('social_posts')->onDelete('set null');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referer', 1024)->nullable();
            $table->timestamp('clicked_at')->useCurrent();
            $table->timestamps();

            $table->index(['social_offer_link_id', 'clicked_at']);
            $table->index(['social_post_id', 'clicked_at']);
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('social_post_clicks');
    }
}
