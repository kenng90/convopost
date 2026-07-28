<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_campaings')) {
            Schema::table('wa_campaings', function (Blueprint $table) {
                if (! Schema::hasColumn('wa_campaings', 'messages_prepared_count')) {
                    $table->unsignedBigInteger('messages_prepared_count')->default(0)->after('send_to');
                }
                if (! Schema::hasColumn('wa_campaings', 'launch_payload')) {
                    $table->json('launch_payload')->nullable()->after('recurrence_next_at');
                }
                if (! Schema::hasColumn('wa_campaings', 'preparation_error')) {
                    $table->text('preparation_error')->nullable()->after('launch_payload');
                }
            });
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (! $this->indexExists('messages', 'messages_dispatch_queue')) {
                    $table->index(['status', 'scchuduled_at', 'campaign_id'], 'messages_dispatch_queue');
                }
                if (! $this->indexExists('messages', 'messages_campaign_status')) {
                    $table->index(['campaign_id', 'status'], 'messages_campaign_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if ($this->indexExists('messages', 'messages_dispatch_queue')) {
                    $table->dropIndex('messages_dispatch_queue');
                }
                if ($this->indexExists('messages', 'messages_campaign_status')) {
                    $table->dropIndex('messages_campaign_status');
                }
            });
        }

        if (Schema::hasTable('wa_campaings')) {
            Schema::table('wa_campaings', function (Blueprint $table) {
                foreach (['messages_prepared_count', 'launch_payload', 'preparation_error'] as $column) {
                    if (Schema::hasColumn('wa_campaings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return ($result[0]->aggregate ?? 0) > 0;
    }
};
