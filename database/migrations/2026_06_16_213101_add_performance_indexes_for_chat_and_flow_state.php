<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_state')) {
            Schema::table('contact_state', function (Blueprint $table) {
                if (! $this->indexExists('contact_state', 'contact_state_lookup')) {
                    $table->unique(['contact_id', 'flow_id', 'state'], 'contact_state_lookup');
                }
            });

            if (! $this->indexExists('contact_state', 'contact_state_value_lookup')) {
                Schema::getConnection()->statement(
                    'CREATE INDEX contact_state_value_lookup ON contact_state (state, value(191))'
                );
            }
        }

        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                if (! $this->indexExists('contacts', 'contacts_inbox')) {
                    $table->index(['company_id', 'has_chat', 'last_reply_at'], 'contacts_inbox');
                }
                if (! $this->indexExists('contacts', 'contacts_inbox_unread')) {
                    $table->index(['company_id', 'has_chat', 'is_last_message_by_contact'], 'contacts_inbox_unread');
                }
            });
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (! $this->indexExists('messages', 'messages_thread')) {
                    $table->index(['contact_id', 'status', 'id'], 'messages_thread');
                }
                if (! $this->indexExists('messages', 'messages_fb_message_id')) {
                    $table->index('fb_message_id', 'messages_fb_message_id');
                }
            });
        }

        if (Schema::hasTable('flowdocuments')) {
            Schema::table('flowdocuments', function (Blueprint $table) {
                if (! $this->indexExists('flowdocuments', 'flowdocuments_flow_id')) {
                    $table->index('flow_id', 'flowdocuments_flow_id');
                }
            });
        }

        if (Schema::hasTable('flows') && Schema::hasColumn('flows', 'flow_data')) {
            Schema::table('flows', function (Blueprint $table) {
                $table->longText('flow_data')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contact_state')) {
            Schema::table('contact_state', function (Blueprint $table) {
                $table->dropUnique('contact_state_lookup');
            });

            if ($this->indexExists('contact_state', 'contact_state_value_lookup')) {
                Schema::getConnection()->statement('DROP INDEX contact_state_value_lookup ON contact_state');
            }
        }

        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropIndex('contacts_inbox');
                $table->dropIndex('contacts_inbox_unread');
            });
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndex('messages_thread');
                $table->dropIndex('messages_fb_message_id');
            });
        }

        if (Schema::hasTable('flowdocuments')) {
            Schema::table('flowdocuments', function (Blueprint $table) {
                $table->dropIndex('flowdocuments_flow_id');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($index) => ($index->name ?? '') === $indexName);
        }

        $database = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT COUNT(*) AS count FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return ($result[0]->count ?? 0) > 0;
    }
};
