<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSocialPostApprovalReviewFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('social_posts')) {
            return;
        }

        Schema::table('social_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_posts', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('approval_status');
            }

            if (! Schema::hasColumn('social_posts', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('submitted_at');
                $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            }

            if (! Schema::hasColumn('social_posts', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }

            if (! Schema::hasColumn('social_posts', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('reviewed_at');
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
        if (! Schema::hasTable('social_posts')) {
            return;
        }

        Schema::table('social_posts', function (Blueprint $table) {
            if (Schema::hasColumn('social_posts', 'reviewed_by')) {
                $table->dropForeign(['reviewed_by']);
            }

            foreach (['submitted_at', 'reviewed_by', 'reviewed_at', 'rejection_reason'] as $column) {
                if (Schema::hasColumn('social_posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
