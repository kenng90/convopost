<?php

use App\Models\ListCatalog;
use App\Services\Catalog\CatalogUrlService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('list_catalogs', function (Blueprint $table) {
            if (! Schema::hasColumn('list_catalogs', 'slug')) {
                $table->string('slug')->nullable()->after('name');
                $table->index(['company_id', 'slug']);
            }
        });

        if (! Schema::hasTable('catalog_analytics_events')) {
            Schema::create('catalog_analytics_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('catalog_id')->constrained('list_catalogs')->cascadeOnDelete();
                $table->string('event_type', 50);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['catalog_id', 'event_type', 'created_at']);
                $table->index(['company_id', 'created_at']);
            });
        }

        ListCatalog::withoutGlobalScopes()
            ->whereNull('slug')
            ->orderBy('id')
            ->each(function (ListCatalog $catalog) {
                $catalog->slug = app(CatalogUrlService::class)->assignSlug($catalog, $catalog->name);
                $catalog->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_analytics_events');

        Schema::table('list_catalogs', function (Blueprint $table) {
            if (Schema::hasColumn('list_catalogs', 'slug')) {
                $table->dropIndex(['company_id', 'slug']);
                $table->dropColumn('slug');
            }
        });
    }
};
