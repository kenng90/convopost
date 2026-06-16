<?php

use App\Models\ListCatalog;
use App\Services\Catalog\CatalogItemRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_items')) {
            Schema::create('catalog_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('catalog_id')->constrained('list_catalogs')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('item_id', 120);
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2)->default(0);
                $table->string('category')->nullable();
                $table->string('image_url', 2048)->nullable();
                $table->string('stock_status', 32)->default('In Stock');
                $table->unsignedInteger('quantity_available')->nullable();
                $table->unsignedInteger('quantity_reserved')->default(0);
                $table->json('variants')->nullable();
                $table->json('tags')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['catalog_id', 'item_id']);
                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('catalog_collections')) {
            Schema::create('catalog_collections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('image_url', 2048)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'slug']);
            });
        }

        if (! Schema::hasTable('catalog_collection_items')) {
            Schema::create('catalog_collection_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('collection_id')->constrained('catalog_collections')->cascadeOnDelete();
                $table->foreignId('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['collection_id', 'catalog_item_id']);
            });
        }

        if (! Schema::hasTable('catalog_item_store_links')) {
            Schema::create('catalog_item_store_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('store_type', 32);
                $table->string('external_product_id', 120);
                $table->string('external_variant_id', 120)->nullable();
                $table->string('external_inventory_item_id', 120)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['store_type', 'external_product_id', 'external_variant_id', 'company_id'], 'catalog_store_link_unique');
            });
        }

        if (! Schema::hasTable('catalog_inventory_reservations')) {
            Schema::create('catalog_inventory_reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('catalog_id')->constrained('list_catalogs')->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->string('status', 32)->default('reserved');
                $table->string('reference_type', 64)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('committed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['catalog_item_id', 'status']);
                $table->index(['expires_at', 'status']);
            });
        }

        if (! Schema::hasTable('catalog_sync_states')) {
            Schema::create('catalog_sync_states', function (Blueprint $table) {
                $table->id();
                $table->foreignId('catalog_id')->constrained('list_catalogs')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('store_type', 32);
                $table->string('direction', 16)->default('bidirectional');
                $table->string('status', 32)->default('idle');
                $table->timestamp('last_pulled_at')->nullable();
                $table->timestamp('last_pushed_at')->nullable();
                $table->text('last_error')->nullable();
                $table->json('webhook_ids')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['catalog_id', 'store_type']);
            });
        }

        Schema::table('list_catalogs', function (Blueprint $table) {
            if (! Schema::hasColumn('list_catalogs', 'experiment_key')) {
                $table->string('experiment_key')->nullable()->after('slug');
                $table->unsignedTinyInteger('traffic_weight')->default(100)->after('experiment_key');
                $table->string('publish_status', 20)->default('published')->after('traffic_weight');
                $table->json('api_config')->nullable()->after('metadata');
            }
        });

        $this->migrateJsonItemsToRelational();
    }

    public function down(): void
    {
        Schema::table('list_catalogs', function (Blueprint $table) {
            foreach (['experiment_key', 'traffic_weight', 'publish_status', 'api_config'] as $column) {
                if (Schema::hasColumn('list_catalogs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('catalog_sync_states');
        Schema::dropIfExists('catalog_inventory_reservations');
        Schema::dropIfExists('catalog_item_store_links');
        Schema::dropIfExists('catalog_collection_items');
        Schema::dropIfExists('catalog_collections');
        Schema::dropIfExists('catalog_items');
    }

    private function migrateJsonItemsToRelational(): void
    {
        if (! Schema::hasTable('catalog_items')) {
            return;
        }

        $repository = app(CatalogItemRepository::class);

        ListCatalog::withoutGlobalScopes()
            ->orderBy('id')
            ->each(function (ListCatalog $catalog) use ($repository) {
                $items = $catalog->getRawOriginal('items');
                if (is_string($items)) {
                    $items = json_decode($items, true) ?: [];
                }
                if (! is_array($items) || $items === []) {
                    return;
                }

                if ($catalog->catalogItems()->exists()) {
                    return;
                }

                $repository->replaceAllFromArray($catalog, $items, syncJson: true);
            });
    }
};
