<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSocialPostVersionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('social_post_versions')) {
            return;
        }

        Schema::create('social_post_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('social_post_id');
            $table->foreign('social_post_id')->references('id')->on('social_posts')->onDelete('cascade');
            $table->string('provider', 50)->default('default');
            $table->text('content')->nullable();
            $table->json('media_ids')->nullable();
            $table->text('first_comment')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'provider'], 'social_post_versions_post_provider_unique');
            $table->index('social_post_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('social_post_versions');
    }
}
