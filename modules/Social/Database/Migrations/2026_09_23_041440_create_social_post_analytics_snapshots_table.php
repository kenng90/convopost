<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSocialPostAnalyticsSnapshotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('social_post_analytics_snapshots')) {
            return;
        }

        Schema::create('social_post_analytics_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unsignedBigInteger('social_post_id');
            $table->foreign('social_post_id')->references('id')->on('social_posts')->onDelete('cascade');
            $table->unsignedBigInteger('social_post_account_id')->nullable();
            $table->foreign('social_post_account_id')->references('id')->on('social_post_accounts')->onDelete('set null');
            $table->string('provider', 30);
            $table->string('provider_post_id')->nullable();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('comments')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('engagement')->default(0);
            $table->json('raw')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'synced_at'], 'social_analytics_company_synced_idx');
            $table->index(['social_post_id', 'synced_at'], 'social_analytics_post_synced_idx');
            $table->index(['social_post_account_id', 'synced_at'], 'social_analytics_account_synced_idx');
            $table->index(['provider', 'provider_post_id'], 'social_analytics_provider_post_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('social_post_analytics_snapshots');
    }
}
