<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSocialOfferLinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('social_offer_links')) {
            return;
        }

        Schema::create('social_offer_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unsignedBigInteger('social_post_id');
            $table->foreign('social_post_id')->references('id')->on('social_posts')->onDelete('cascade');
            $table->string('offer_type', 50)->default('url');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('url')->nullable();
            $table->string('tracking_token', 64)->unique();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'social_post_id']);
            $table->index('tracking_token');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('social_offer_links');
    }
}
