<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSocialCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('social_comments')) {
            return;
        }

        Schema::create('social_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unsignedBigInteger('social_post_id')->nullable();
            $table->foreign('social_post_id')->references('id')->on('social_posts')->onDelete('cascade');
            $table->unsignedBigInteger('social_post_account_id')->nullable();
            $table->foreign('social_post_account_id')->references('id')->on('social_post_accounts')->onDelete('set null');
            $table->unsignedBigInteger('social_account_id')->nullable();
            $table->foreign('social_account_id')->references('id')->on('social_accounts')->onDelete('set null');
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('provider', 30);
            $table->string('provider_comment_id');
            $table->string('provider_post_id')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_username')->nullable();
            $table->string('author_external_id')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('commented_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'provider', 'provider_comment_id'],
                'social_comments_company_provider_comment_uidx'
            );
            $table->index(['company_id', 'commented_at'], 'social_comments_company_commented_idx');
            $table->index(['social_post_id', 'commented_at'], 'social_comments_post_commented_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('social_comments');
    }
}
