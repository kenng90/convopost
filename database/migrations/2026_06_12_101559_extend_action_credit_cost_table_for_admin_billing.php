<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('action_credit_cost')) {
            return;
        }

        Schema::table('action_credit_cost', function (Blueprint $table) {
            if (! Schema::hasColumn('action_credit_cost', 'label')) {
                $table->string('label')->nullable()->after('action');
            }
            if (! Schema::hasColumn('action_credit_cost', 'module')) {
                $table->string('module')->nullable()->after('label');
            }
            if (! Schema::hasColumn('action_credit_cost', 'category')) {
                $table->string('category')->default('other')->after('module');
            }
            if (! Schema::hasColumn('action_credit_cost', 'default_cost')) {
                $table->integer('default_cost')->default(1)->after('category');
            }
            if (! Schema::hasColumn('action_credit_cost', 'help')) {
                $table->text('help')->nullable()->after('default_cost');
            }
            if (! Schema::hasColumn('action_credit_cost', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(100)->after('help');
            }
            if (! Schema::hasColumn('action_credit_cost', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('action_credit_cost', function (Blueprint $table) {
            $table->dropColumn([
                'label',
                'module',
                'category',
                'default_cost',
                'help',
                'sort_order',
                'is_active',
            ]);
        });
    }
};
