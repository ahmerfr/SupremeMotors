<?php

namespace App\Console\Commands;

use App\Support\MongoExtendedJson;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportMongoJson extends Command
{
    protected $signature = 'mongo:import
        {--only=* : Limit to specific collections (categories, users, contact_forms, newsletters, query_forms, products)}
        {--path=mongodb-json : Directory containing the NDJSON exports}';

    protected $description = 'Import MongoDB NDJSON exports into MySQL';

    private const BATCH_ROWS = 200;
    private const BATCH_BYTES = 500_000; // stay under XAMPP 1MB max_allowed_packet

    public function handle(): int
    {
        $only = $this->option('only');
        $run = fn (string $name) => empty($only) || in_array($name, $only, true);

        // Order matters: categories before products (FK remap).
        if ($run('categories')) {
            $this->importCategories();
        }
        if ($run('users')) {
            $this->importUsers();
        }
        if ($run('contact_forms')) {
            $this->importContactForms();
        }
        if ($run('newsletters')) {
            $this->importNewsletters();
        }
        if ($run('query_forms')) {
            $this->importQueryForms();
        }
        if ($run('products')) {
            $this->importProducts();
        }

        return self::SUCCESS;
    }

    /** Stream one decoded document per NDJSON line without loading the file. */
    private function documents(string $file): Generator
    {
        $path = base_path($this->option('path').DIRECTORY_SEPARATOR.$file);
        if (! is_file($path) || filesize($path) === 0) {
            $this->warn("Skipping {$file}: missing or empty.");

            return;
        }
        $handle = fopen($path, 'rb');
        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                yield MongoExtendedJson::normalize(json_decode($line, true, 512, JSON_THROW_ON_ERROR));
            }
        } finally {
            fclose($handle);
        }
    }

    private function truncate(string $table): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table($table)->truncate();
        Schema::enableForeignKeyConstraints();
    }

    private function importCategories(): void
    {
        $this->truncate('categories');
        $rows = [];
        foreach ($this->documents('categories.json') as $doc) {
            $rows[] = [
                'mongo_id' => $doc['_id'],
                'cat_title' => $doc['cat_title'] ?? '',
                'description' => $doc['description'] ?? null,
                'type' => $doc['type'] ?? 'category',
                'image' => $doc['image'] ?? null,
                'created_at' => $doc['created_at'] ?? now(),
                'updated_at' => $doc['updated_at'] ?? now(),
            ];
        }
        foreach (array_chunk($rows, self::BATCH_ROWS) as $chunk) {
            DB::table('categories')->insert($chunk);
        }
        $this->info('categories: '.count($rows).' imported.');
    }

    private function importUsers(): void
    {
        $this->truncate('users');
        $rows = [];
        foreach ($this->documents('users.json') as $doc) {
            $rows[] = [
                'name' => $doc['name'] ?? '',
                'email' => $doc['email'],
                'password' => $doc['password'] ?? '',
                'role' => $doc['role'] ?? 'user',
                'profile_picture' => $doc['profile_picture'] ?? null,
                'email_verified_at' => $doc['email_verified_at'] ?? null,
                'created_at' => $doc['created_at'] ?? now(),
                'updated_at' => $doc['updated_at'] ?? now(),
            ];
        }
        foreach (array_chunk($rows, self::BATCH_ROWS) as $chunk) {
            DB::table('users')->insert($chunk);
        }
        $this->info('users: '.count($rows).' imported.');
    }

    private function importContactForms(): void
    {
        $this->truncate('contact_form');
        $rows = [];
        foreach ($this->documents('contact_forms.json') as $doc) {
            $rows[] = [
                'name' => $doc['name'] ?? '',
                'email' => $doc['email'] ?? '',
                'phone' => $doc['phone'] ?? null,
                'subject' => mb_substr($doc['subject'] ?? '', 0, 500),
                'message' => $doc['message'] ?? null,
                'consent' => (bool) ($doc['consent'] ?? false),
                'created_at' => $doc['created_at'] ?? now(),
                'updated_at' => $doc['updated_at'] ?? now(),
            ];
        }
        foreach (array_chunk($rows, self::BATCH_ROWS) as $chunk) {
            DB::table('contact_form')->insert($chunk);
        }
        $this->info('contact_form: '.count($rows).' imported.');
    }

    private function importNewsletters(): void
    {
        $this->truncate('newsletter_entry');
        $rows = [];
        foreach ($this->documents('newsletters.json') as $doc) {
            $rows[] = [
                'email' => $doc['email'],
                'created_at' => $doc['created_at'] ?? now(),
                'updated_at' => $doc['updated_at'] ?? now(),
            ];
        }
        foreach (array_chunk($rows, self::BATCH_ROWS) as $chunk) {
            DB::table('newsletter_entry')->insert($chunk);
        }
        $this->info('newsletter_entry: '.count($rows).' imported.');
    }

    private function importQueryForms(): void
    {
        $this->truncate('queryform_entry');
        $rows = [];
        foreach ($this->documents('query_forms.json') as $doc) {
            $rows[] = [
                'company' => $doc['company'] ?? null,
                'contact_name' => $doc['contact_name'] ?? null,
                'email' => $doc['email'] ?? '',
                'phone' => $doc['phone'] ?? null,
                'meeting' => $doc['meeting'] ?? null,
                'visit' => $doc['visit'] ?? null,
                'closing' => isset($doc['closing']) ? (int) $doc['closing'] : null,
                'message' => $doc['message'] ?? null,
                'created_at' => $doc['created_at'] ?? now(),
                'updated_at' => $doc['updated_at'] ?? now(),
            ];
        }
        foreach (array_chunk($rows, self::BATCH_ROWS) as $chunk) {
            DB::table('queryform_entry')->insert($chunk);
        }
        $this->info('queryform_entry: '.count($rows).' imported.');
    }

    private function importProducts(): void
    {
        $this->truncate('products');
        $catMap = DB::table('categories')->whereNotNull('mongo_id')->pluck('id', 'mongo_id')->all();

        $batch = [];
        $batchBytes = 0;
        $total = 0;
        $unmatchedCategory = 0;
        $unmatchedMake = 0;

        $flush = function () use (&$batch, &$batchBytes) {
            if ($batch !== []) {
                DB::table('products')->insert($batch);
                $batch = [];
                $batchBytes = 0;
            }
        };

        foreach ($this->documents('products.json') as $doc) {
            $categoryId = $catMap[$doc['category_id'] ?? ''] ?? null;
            $makeId = $catMap[$doc['make_id'] ?? ''] ?? null;
            if ($categoryId === null && ! empty($doc['category_id'])) {
                $unmatchedCategory++;
            }
            if ($makeId === null && ! empty($doc['make_id'])) {
                $unmatchedMake++;
            }

            $row = [
                'mongo_id' => $doc['_id'],
                'title' => mb_substr($doc['title'] ?? '', 0, 500),
                'category_id' => $categoryId,
                'make_id' => $makeId,
                'price' => MongoExtendedJson::parsePrice($doc['price'] ?? 0),
                'country' => $doc['country'] ?? null,
                'website' => $doc['website'] ?? null,
                'body_style' => $doc['body_style'] ?? null,
                'product_link' => $doc['product_link'] ?? null,
                'front_image' => mb_substr($doc['front_image'] ?? '', 0, 500) ?: null,
                'other_images' => json_encode($doc['other_images'] ?? [], JSON_UNESCAPED_SLASHES),
                'product_details' => $doc['product_details'] ?? null,
                'created_at' => $doc['created_at'] ?? now(),
                'updated_at' => $doc['updated_at'] ?? now(),
            ];

            $batch[] = $row;
            $batchBytes += strlen($row['product_details'] ?? '') + strlen($row['other_images']) + 500;
            $total++;

            if (count($batch) >= self::BATCH_ROWS || $batchBytes >= self::BATCH_BYTES) {
                $flush();
            }
            if ($total % 20000 === 0) {
                $this->info("products: {$total} inserted...");
            }
        }
        $flush();

        $this->info("products: {$total} imported. Unmatched category refs: {$unmatchedCategory}; unmatched make refs: {$unmatchedMake}.");
    }
}
