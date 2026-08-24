<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'collection_status')) {
                $table->string('collection_status', 32)->default('draft')->after('status');
            }
            if (! Schema::hasColumn('invoices', 'due_at')) {
                $table->timestamp('due_at')->nullable()->after('collection_status');
            }
            if (! Schema::hasColumn('invoices', 'next_chase_at')) {
                $table->timestamp('next_chase_at')->nullable()->after('due_at');
            }
            if (! Schema::hasColumn('invoices', 'chase_step')) {
                $table->unsignedTinyInteger('chase_step')->default(0)->after('next_chase_at');
            }
            if (! Schema::hasColumn('invoices', 'collection_channel')) {
                $table->string('collection_channel', 32)->nullable()->after('chase_step');
            }
            if (! Schema::hasColumn('invoices', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')->nullable()->after('collection_channel')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['collection_status', 'next_chase_at'], 'invoices_collection_chase_index');
            $table->index('company_id', 'invoices_company_collection_index');
        });

        DB::table('invoices')->orderBy('id')->chunkById(200, function ($invoices) {
            foreach ($invoices as $invoice) {
                $status = match ($invoice->status) {
                    'paid' => 'paid',
                    'cancelled' => 'cancelled',
                    'failed' => 'failed',
                    'pending' => 'requested',
                    'sent' => 'due',
                    default => 'draft',
                };

                DB::table('invoices')->where('id', $invoice->id)->update([
                    'collection_status' => $status,
                    'due_at' => $invoice->sent_at ?? $invoice->created_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'assigned_user_id')) {
                $table->dropConstrainedForeignId('assigned_user_id');
            }
            foreach (['collection_status', 'due_at', 'next_chase_at', 'chase_step', 'collection_channel'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
