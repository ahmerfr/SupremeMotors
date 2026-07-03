<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Single ALTER: one table rebuild.
        DB::statement('ALTER TABLE products MODIFY created_at DATETIME(6) NULL DEFAULT NULL');

        // Scraped batches share one-second timestamps (up to 19K rows per
        // tie), and ORDER BY created_at DESC breaks those ties by reverse
        // insertion order — each batch displayed oldest-first. Spread ties
        // across microseconds so the earliest-inserted row of a tie sorts
        // newest, without giving up the index-backed sort.
        DB::statement(<<<'SQL'
            UPDATE products p
            JOIN (
                SELECT id, TIMESTAMPADD(MICROSECOND, cnt - rn, created_at) AS new_ts
                FROM (
                    SELECT id, created_at,
                           ROW_NUMBER() OVER (PARTITION BY created_at ORDER BY id) AS rn,
                           COUNT(*)     OVER (PARTITION BY created_at)             AS cnt
                    FROM products
                ) x
                WHERE cnt > 1
            ) g ON g.id = p.id
            SET p.created_at = g.new_ts
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY created_at DATETIME NULL DEFAULT NULL');
    }
};
