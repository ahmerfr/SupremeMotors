<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * product_link is the scraper's dedup + upsert key (updateOrCreate and the
 * "already scraped?" skip both look it up). It's a TEXT column, so without an
 * index every one of the ~92k lookups during a scrape is a full-table scan
 * over 380k+ rows. MySQL can't index a bare TEXT column, so we add a prefix
 * index (255 chars is longer than any listing URL, so lookups stay exact).
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasIndex()) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products ADD INDEX products_link_idx (product_link(255))');
        } else {
            // sqlite/others (tests): full-column index is fine
            Schema::table('products', fn ($t) => $t->index('product_link', 'products_link_idx'));
        }
    }

    public function down(): void
    {
        if ($this->hasIndex()) {
            Schema::table('products', fn ($t) => $t->dropIndex('products_link_idx'));
        }
    }

    private function hasIndex(): bool
    {
        try {
            return collect(DB::select("SHOW INDEX FROM products WHERE Key_name = 'products_link_idx'"))->isNotEmpty();
        } catch (\Throwable) {
            // sqlite path
            return collect(Schema::getIndexes('products'))->contains(fn ($i) => $i['name'] === 'products_link_idx');
        }
    }
};
