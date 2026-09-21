<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSocialAttributionToInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'social_post_id')) {
                $table->unsignedBigInteger('social_post_id')->nullable()->after('catalog_id');
                $table->foreign('social_post_id')->references('id')->on('social_posts')->onDelete('set null');
            }

            if (! Schema::hasColumn('invoices', 'social_offer_link_id')) {
                $table->unsignedBigInteger('social_offer_link_id')->nullable()->after('social_post_id');
                $table->foreign('social_offer_link_id')->references('id')->on('social_offer_links')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'social_offer_link_id')) {
                $table->dropForeign(['social_offer_link_id']);
                $table->dropColumn('social_offer_link_id');
            }

            if (Schema::hasColumn('invoices', 'social_post_id')) {
                $table->dropForeign(['social_post_id']);
                $table->dropColumn('social_post_id');
            }
        });
    }
}
