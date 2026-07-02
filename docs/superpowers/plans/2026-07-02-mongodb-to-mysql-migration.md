# MongoDB → MySQL Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate SupremeMotors (Laravel 12 + Inertia/Vue) from MongoDB Atlas to local MySQL (XAMPP MariaDB 10.4), importing all data from `mongodb-json/` NDJSON exports and converting every query to optimized SQL.

**Architecture:** Relational schema with auto-increment integer PKs; legacy Mongo ObjectIds preserved in `mongo_id` columns on `categories`/`products` for FK remapping and old-URL fallback. A streaming artisan importer (`mongo:import`) reads the 1.5 GB `products.json` line-by-line with byte-capped batch inserts. Mongo aggregation pipelines become indexed JOIN/GROUP BY queries; `regex` search becomes FULLTEXT (boolean mode) with LIKE fallback. Frontend `_id` refs become `id`.

**Tech Stack:** PHP 8.2, Laravel 12, MariaDB 10.4 (XAMPP, `C:\xampp\mysql`), Inertia + Vue 3, PHPUnit (sqlite `:memory:` for tests).

## Global Constraints

- Database name: `supreme_motors`; connection `mysql`, host `127.0.0.1`, port `3306`, user `root`, empty password (XAMPP default).
- MariaDB 10.4 = XAMPP default; `max_allowed_packet` may be as low as 1 MB → importer must cap batches by **bytes** (500 KB) as well as row count.
- Tests run on sqlite `:memory:` (see `phpunit.xml`) → any FULLTEXT/REGEXP-specific migration code must be guarded with `DB::getDriverName()` checks so `migrate` works on sqlite.
- Route URLs must not change. Public product-detail URLs previously contained ObjectId hex → `product_detail` must accept both numeric ids and legacy 24-char hex (`mongo_id` lookup).
- Do NOT import `cache.json`, `sessions.json` (ephemeral runtime data; Laravel's default `cache`/`sessions` tables are recreated by existing migrations). `blogs.json` and `test.json` are 0 bytes — skip.
- `products.json` facts (measured): 453,375 lines; all lines have `_id,title,category_id,price,front_image,other_images,product_details,created_at,updated_at,country,website`; only 228,043 have `make_id`, 171,896 have `body_style` and `product_link`. `price` is a string like `"24,920"` or `""`. `category_id`/`make_id` are plain hex **strings** referencing `categories._id`.
- Keep existing table names the models already point at: `contact_form`, `newsletter_entry`, `queryform_entry`, `products`, `categories`, `blogs`, `users`.
- `.env` is permission-sensitive; edit it via the Edit tool (values listed in Task 2). Never print the old `MONGODB_URI` into logs or commits — `.env` is gitignored.
- Project is NOT yet a git repo — Task 1 initializes it.
- Commit messages end with `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.

---

### Task 1: Git init + baseline commit

**Files:**
- Create: `.git/` (init), verify `.gitignore` exists (Laravel starter kit ships one covering `/vendor`, `/node_modules`, `.env`)

**Interfaces:**
- Produces: git history so every later task can commit atomically.

- [ ] **Step 1: Init and verify ignore rules**

```bash
cd /c/xampp/htdocs/SupremeMotors
git init -b main
grep -E "^/vendor|^\.env|node_modules" .gitignore
```
Expected: `.gitignore` lists `/vendor`, `/node_modules`, `.env`. If `.env` is not ignored, add it before committing.

Also append these lines to `.gitignore` (huge data + junk must never be committed):

```
/mongodb-json
github.zip
error_log
desktop.ini
```

- [ ] **Step 2: Baseline commit**

```bash
git add -A
git commit -m "chore: baseline before MongoDB to MySQL migration

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```
Expected: commit succeeds; `git status` clean (mongodb-json/ untracked-ignored).

---

### Task 2: Point Laravel at MySQL + full relational schema

**Files:**
- Modify: `.env` (DB block)
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php` (add `role`, `profile_picture`)
- Create: `database/migrations/2026_07_02_000001_create_categories_table.php`
- Create: `database/migrations/2026_07_02_000002_create_products_table.php`
- Create: `database/migrations/2026_07_02_000003_create_blogs_table.php`
- Create: `database/migrations/2026_07_02_000004_create_contact_form_table.php`
- Create: `database/migrations/2026_07_02_000005_create_newsletter_entry_table.php`
- Create: `database/migrations/2026_07_02_000006_create_queryform_entry_table.php`

**Interfaces:**
- Produces: tables `users(id, name, email UNIQUE, password, role, profile_picture, email_verified_at, remember_token, timestamps)`, `categories(id, mongo_id CHAR(24) UNIQUE, cat_title, description, type, image, timestamps)`, `products(id, mongo_id CHAR(24) UNIQUE, title, category_id FK→categories nullable, make_id FK→categories nullable, price DECIMAL(12,2) NOT NULL DEFAULT 0, country, website, body_style, product_link, front_image, other_images JSON, product_details MEDIUMTEXT, timestamps)`, plus `blogs`, `contact_form`, `newsletter_entry`, `queryform_entry`.
- NOTE: heavy secondary indexes + FULLTEXT are deliberately **deferred to Task 6** (post-import) so the 453K-row bulk insert isn't throttled by index maintenance. Only PK/UNIQUE/FK indexes here.

- [ ] **Step 1: Start MariaDB and create the database**

```powershell
Start-Process -FilePath "C:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini","--standalone" -WindowStyle Hidden
```
Wait ~5s, then:
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS supreme_motors CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; SELECT VERSION();"
```
Expected: prints `10.4.32-MariaDB`. (If XAMPP Control Panel is preferred, starting MySQL there is equivalent.)

- [ ] **Step 2: Update `.env`**

Replace the DB section values (keep everything else):

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=supreme_motors
DB_USERNAME=root
DB_PASSWORD=
```
Leave `MONGODB_URI`/`MONGODB_DATABASE` in place for now (removed in Task 12 after code stops referencing the connection). `SESSION_DRIVER=database` and `CACHE_STORE=database` stay — their tables come from the stock migrations.

- [ ] **Step 3: Add `role` + `profile_picture` to the users migration**

In `database/migrations/0001_01_01_000000_create_users_table.php`, inside `Schema::create('users', ...)` after the `password` column add:

```php
$table->string('role', 20)->default('user')->index();
$table->text('profile_picture')->nullable();
```
(Fresh database — editing the base migration is safe; nothing is deployed on MySQL yet.)

- [ ] **Step 4: Create the six schema migrations**

`database/migrations/2026_07_02_000001_create_categories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->char('mongo_id', 24)->nullable()->unique();
            $table->string('cat_title');
            $table->string('description')->nullable();
            $table->string('type', 20)->index();
            $table->string('image', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

`database/migrations/2026_07_02_000002_create_products_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->char('mongo_id', 24)->nullable()->unique();
            $table->string('title', 500);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('make_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('country', 100)->nullable();
            $table->string('website', 100)->nullable();
            $table->string('body_style', 100)->nullable();
            $table->text('product_link')->nullable();
            $table->string('front_image', 500)->nullable();
            $table->json('other_images')->nullable();
            $table->mediumText('product_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

`database/migrations/2026_07_02_000003_create_blogs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->longText('content');
            $table->string('cover_image', 500)->nullable();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->string('publish_status', 20)->default('draft')->index();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
```

`database/migrations/2026_07_02_000004_create_contact_form_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_form', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone', 50)->nullable();
            $table->string('subject', 500)->nullable();
            $table->text('message')->nullable();
            $table->boolean('consent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_form');
    }
};
```

`database/migrations/2026_07_02_000005_create_newsletter_entry_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_entry', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_entry');
    }
};
```

`database/migrations/2026_07_02_000006_create_queryform_entry_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queryform_entry', function (Blueprint $table) {
            $table->id();
            $table->string('company')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->index();
            $table->string('phone', 50)->nullable();
            $table->string('meeting', 20)->nullable();
            $table->string('visit', 20)->nullable();
            $table->integer('closing')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queryform_entry');
    }
};
```

- [ ] **Step 5: Run migrations**

```bash
cd /c/xampp/htdocs/SupremeMotors && php artisan migrate
```
Expected: all migrations (users/cache/jobs + the six new ones) run without error.

Verify:
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root supreme_motors -e "SHOW TABLES;"
```
Expected: `blogs, cache, cache_locks, categories, contact_form, failed_jobs, job_batches, jobs, migrations, newsletter_entry, password_reset_tokens, products, queryform_entry, sessions, users`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations .env.example
git commit -m "feat: MySQL schema for all collections

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: Mongo Extended-JSON normalizer (TDD)

**Files:**
- Create: `app/Support/MongoExtendedJson.php`
- Test: `tests/Unit/MongoExtendedJsonTest.php`

**Interfaces:**
- Produces: `App\Support\MongoExtendedJson::normalize(array $doc): array` — recursively flattens `{"$oid":…}`, `{"$date":{"$numberLong":ms}}`, `{"$numberInt":…}`, `{"$numberLong":…}`, `{"$numberDouble":…}` into scalars; dates become `'Y-m-d H:i:s'` strings.
- Produces: `App\Support\MongoExtendedJson::parsePrice(mixed $raw): float` — `"24,920"` → `24920.0`, `"1234 USD"` → `1234.0`, `""`/null → `0.0`.
- Consumed by: Task 4/5 importer.

- [ ] **Step 1: Write the failing test**

`tests/Unit/MongoExtendedJsonTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\MongoExtendedJson;
use PHPUnit\Framework\TestCase;

class MongoExtendedJsonTest extends TestCase
{
    public function test_flattens_oid(): void
    {
        $doc = ['_id' => ['$oid' => '67e7cd7b6a5af0e3790dbc6c'], 'title' => 'x'];
        $out = MongoExtendedJson::normalize($doc);
        $this->assertSame('67e7cd7b6a5af0e3790dbc6c', $out['_id']);
        $this->assertSame('x', $out['title']);
    }

    public function test_converts_date_number_long_ms_to_datetime_string(): void
    {
        $doc = ['created_at' => ['$date' => ['$numberLong' => '1743244666940']]];
        $out = MongoExtendedJson::normalize($doc);
        $this->assertSame('2025-03-29 10:37:46', $out['created_at']);
    }

    public function test_converts_iso_date_string(): void
    {
        $doc = ['created_at' => ['$date' => '2025-03-29T10:37:46.940Z']];
        $out = MongoExtendedJson::normalize($doc);
        $this->assertSame('2025-03-29 10:37:46', $out['created_at']);
    }

    public function test_converts_numeric_wrappers(): void
    {
        $doc = [
            'a' => ['$numberInt' => '50'],
            'b' => ['$numberLong' => '1750778211'],
            'c' => ['$numberDouble' => '1.5'],
        ];
        $out = MongoExtendedJson::normalize($doc);
        $this->assertSame(50, $out['a']);
        $this->assertSame(1750778211, $out['b']);
        $this->assertSame(1.5, $out['c']);
    }

    public function test_recurses_into_plain_arrays_and_leaves_scalars(): void
    {
        $doc = [
            'images' => ['a.jpg', 'b.jpg'],
            'nested' => ['id' => ['$oid' => 'abcabcabcabcabcabcabcabc']],
            'flag' => true,
        ];
        $out = MongoExtendedJson::normalize($doc);
        $this->assertSame(['a.jpg', 'b.jpg'], $out['images']);
        $this->assertSame('abcabcabcabcabcabcabcabc', $out['nested']['id']);
        $this->assertTrue($out['flag']);
    }

    public function test_parse_price_variants(): void
    {
        $this->assertSame(24920.0, MongoExtendedJson::parsePrice('24,920'));
        $this->assertSame(1234.0, MongoExtendedJson::parsePrice('1234 USD'));
        $this->assertSame(0.0, MongoExtendedJson::parsePrice(''));
        $this->assertSame(0.0, MongoExtendedJson::parsePrice(null));
        $this->assertSame(52000.5, MongoExtendedJson::parsePrice(52000.5));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MongoExtendedJsonTest`
Expected: FAIL — `Class "App\Support\MongoExtendedJson" not found`.

- [ ] **Step 3: Write the implementation**

`app/Support/MongoExtendedJson.php`:

```php
<?php

namespace App\Support;

use Carbon\Carbon;

class MongoExtendedJson
{
    /**
     * Recursively flatten MongoDB Extended JSON (v2) wrappers into plain scalars.
     */
    public static function normalize(array $doc): array
    {
        $out = [];
        foreach ($doc as $key => $value) {
            $out[$key] = is_array($value) ? self::normalizeValue($value) : $value;
        }

        return $out;
    }

    private static function normalizeValue(array $value): mixed
    {
        if (array_key_exists('$oid', $value)) {
            return $value['$oid'];
        }
        if (array_key_exists('$date', $value)) {
            $date = $value['$date'];
            if (is_array($date) && isset($date['$numberLong'])) {
                return Carbon::createFromTimestampMs((int) $date['$numberLong'], 'UTC')
                    ->format('Y-m-d H:i:s');
            }

            return Carbon::parse($date)->utc()->format('Y-m-d H:i:s');
        }
        if (array_key_exists('$numberInt', $value) || array_key_exists('$numberLong', $value)) {
            return (int) ($value['$numberInt'] ?? $value['$numberLong']);
        }
        if (array_key_exists('$numberDouble', $value) || array_key_exists('$numberDecimal', $value)) {
            return (float) ($value['$numberDouble'] ?? $value['$numberDecimal']);
        }

        return self::normalize($value);
    }

    /**
     * "24,920" / "1234 USD" / "" / null / 52000.5 -> float.
     */
    public static function parsePrice(mixed $raw): float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $clean = preg_replace('/[^0-9.]/', '', (string) $raw);

        return $clean === '' ? 0.0 : (float) $clean;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MongoExtendedJsonTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/MongoExtendedJson.php tests/Unit/MongoExtendedJsonTest.php
git commit -m "feat: Mongo extended-JSON normalizer with price parser

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: `mongo:import` command — small collections

**Files:**
- Create: `app/Console/Commands/ImportMongoJson.php`

**Interfaces:**
- Consumes: `MongoExtendedJson::normalize/parsePrice` (Task 3).
- Produces: `php artisan mongo:import {--only=* } {--path=mongodb-json}`. Truncates each target table before importing (idempotent re-runs). Collections: `categories`, `users`, `contact_forms`, `newsletters`, `query_forms`, `products` (products wired in Task 5).

- [ ] **Step 1: Write the command**

`app/Console/Commands/ImportMongoJson.php`:

```php
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
        // Implemented in Task 5.
        $this->warn('products import not yet implemented.');
    }
}
```

- [ ] **Step 2: Run small-collection import**

```bash
php artisan mongo:import --only=categories --only=users --only=contact_forms --only=newsletters --only=query_forms
```
Expected output: `categories: 132 imported.` `users: 34 imported.` `contact_form: 37 imported.` `newsletter_entry: 15 imported.` `queryform_entry: 15 imported.`

- [ ] **Step 3: Verify data**

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root supreme_motors -e "SELECT COUNT(*) c FROM categories; SELECT id, mongo_id, cat_title, type FROM categories LIMIT 3; SELECT email, role FROM users LIMIT 3;"
```
Expected: 132 categories; `mongo_id` populated with 24-char hex; users show bcrypt-imported accounts with correct roles.

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/ImportMongoJson.php
git commit -m "feat: mongo:import command for small collections

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: Products import (streaming, 453K rows)

**Files:**
- Modify: `app/Console/Commands/ImportMongoJson.php` (replace `importProducts()` stub)

**Interfaces:**
- Consumes: `categories.mongo_id → id` map from Task 4 import.
- Produces: 453,375 rows in `products`, with `category_id`/`make_id` remapped to integer FKs (unmatched refs → NULL, counted and reported), `price` as DECIMAL, `other_images` as JSON.

- [ ] **Step 1: Implement `importProducts()`**

Replace the stub in `ImportMongoJson.php`:

```php
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
```

- [ ] **Step 2: Run the products import**

```bash
php artisan mongo:import --only=products
```
Expected: progress lines every 20K; final line `products: 453375 imported. …`. (Several minutes for 1.5 GB — run with a generous timeout or in the background.)

- [ ] **Step 3: Verify counts and remapping**

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root supreme_motors -e "SELECT COUNT(*) total, COUNT(category_id) with_cat, COUNT(make_id) with_make, COUNT(body_style) with_body FROM products; SELECT MIN(price), MAX(price) FROM products; SELECT p.title, c.cat_title FROM products p JOIN categories c ON c.id=p.category_id LIMIT 3;"
```
Expected: `total = 453375`; `with_make ≈ 228043`; `with_body ≈ 171896`; join returns sensible category titles; prices numeric.

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/ImportMongoJson.php
git commit -m "feat: streaming products import with FK remap

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: Post-import indexes (composite + FULLTEXT)

**Files:**
- Create: `database/migrations/2026_07_02_100000_add_products_indexes.php`

**Interfaces:**
- Produces indexes consumed by Tasks 8–10 queries:
  - `products`: `(category_id, make_id)`, `(category_id, country)`, `(body_style)`, `(country)`, `(website, created_at)`, `(created_at)`, `(price)`, FULLTEXT `(title, product_details)` *(mysql/mariadb only)*
  - `categories`: `(type, created_at)`

- [ ] **Step 1: Create the migration**

`database/migrations/2026_07_02_100000_add_products_indexes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'make_id'], 'products_category_make_idx');
            $table->index(['category_id', 'country'], 'products_category_country_idx');
            $table->index('body_style', 'products_body_style_idx');
            $table->index('country', 'products_country_idx');
            $table->index(['website', 'created_at'], 'products_website_created_idx');
            $table->index('created_at', 'products_created_idx');
            $table->index('price', 'products_price_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['type', 'created_at'], 'categories_type_created_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products ADD FULLTEXT products_search_ft (title, product_details)');
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_category_make_idx');
            $table->dropIndex('products_category_country_idx');
            $table->dropIndex('products_body_style_idx');
            $table->dropIndex('products_country_idx');
            $table->dropIndex('products_website_created_idx');
            $table->dropIndex('products_created_idx');
            $table->dropIndex('products_price_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_type_created_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products DROP INDEX products_search_ft');
        }
    }
};
```

- [ ] **Step 2: Run it (long-running — FULLTEXT over 453K HTML docs)**

Run: `php artisan migrate` (allow up to 10 minutes).
Expected: only `2026_07_02_100000_add_products_indexes` runs.

- [ ] **Step 3: Verify indexes + FULLTEXT works**

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root supreme_motors -e "SHOW INDEX FROM products; SELECT COUNT(*) FROM products WHERE MATCH(title, product_details) AGAINST('+shacman*' IN BOOLEAN MODE);"
```
Expected: all named indexes listed; FULLTEXT count > 0.

- [ ] **Step 4: Confirm tests still pass on sqlite** (guard works)

Run: `php artisan test --filter=MongoExtendedJsonTest`
Expected: PASS (feature tests touching migrations must not explode; the FULLTEXT statement is skipped on sqlite).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_02_100000_add_products_indexes.php
git commit -m "feat: query indexes and fulltext search index for products

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: Convert models to SQL Eloquent

**Files:**
- Modify: `app/Models/User.php`, `app/Models/Products.php`, `app/Models/Categories.php`, `app/Models/Blogs.php`, `app/Models/ContactForm.php`, `app/Models/Newsletter.php`, `app/Models/QueryForm.php`

**Interfaces:**
- Produces: `Products::category()` / `Products::make()` as `belongsTo`; `Products::scopeSearch(string $term)` (FULLTEXT boolean-mode with LIKE fallback for short terms) consumed by Task 10; `price` cast to `float` so Vue's `.toLocaleString()` gets a number; `other_images`/`tags` cast to `array`.

- [ ] **Step 1: Rewrite the models**

`app/Models/User.php`:

```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'profile_picture',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

`app/Models/Products.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'mongo_id',
        'title',
        'category_id',
        'make_id',
        'price',
        'website',
        'country',
        'body_style',
        'product_link',
        'front_image',
        'other_images',
        'product_details',
    ];

    protected $casts = [
        'price' => 'float',
        'other_images' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function make()
    {
        return $this->belongsTo(Categories::class, 'make_id');
    }

    /**
     * Full-text search on title + product_details. Boolean-mode prefix match
     * (backed by the products_search_ft index); falls back to LIKE when every
     * word is shorter than the fulltext minimum token size (3) or on sqlite.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $boolean = collect(preg_split('/\s+/', $term))
            ->map(fn ($word) => preg_replace('/[+\-<>()~*"@]+/', '', $word))
            ->filter(fn ($word) => mb_strlen($word) >= 3)
            ->map(fn ($word) => '+'.$word.'*')
            ->implode(' ');

        if ($boolean === '' || ! in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('product_details', 'like', "%{$term}%");
            });
        }

        return $query->whereRaw('MATCH(title, product_details) AGAINST(? IN BOOLEAN MODE)', [$boolean]);
    }
}
```

`app/Models/Categories.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'mongo_id',
        'cat_title',
        'description',
        'image',
        'type',
    ];

    public function cat_products()
    {
        return $this->hasMany(Products::class, 'category_id');
    }

    public function make_products()
    {
        return $this->hasMany(Products::class, 'make_id');
    }
}
```

`app/Models/Blogs.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blogs extends Model
{
    use HasFactory;

    protected $table = 'blogs';

    protected $fillable = [
        'title',
        'slug',
        'short_description',
        'content',
        'cover_image',
        'category',
        'tags',
        'publish_status',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}
```

`app/Models/ContactForm.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactForm extends Model
{
    use HasFactory;

    protected $table = 'contact_form';

    protected $casts = [
        'consent' => 'boolean',
    ];
}
```

`app/Models/Newsletter.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    use HasFactory;

    protected $table = 'newsletter_entry';
}
```

`app/Models/QueryForm.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueryForm extends Model
{
    use HasFactory;

    protected $table = 'queryform_entry';
}
```

- [ ] **Step 2: Verify no MongoDB imports remain in models**

Run: `grep -rn "MongoDB" app/Models/`
Expected: no matches.

- [ ] **Step 3: Smoke-check via tinker**

```bash
php artisan tinker --execute="echo App\Models\Products::count() . PHP_EOL; echo App\Models\Products::with('category')->first()->category?->cat_title . PHP_EOL; echo App\Models\Products::search('shacman')->count() . PHP_EOL;"
```
Expected: `453375`, a category title, and a non-zero search count.

- [ ] **Step 4: Commit**

```bash
git add app/Models
git commit -m "refactor: convert models from MongoDB to SQL Eloquent

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 8: Base Controller + AdminController conversion

**Files:**
- Modify: `app/Http/Controllers/Controller.php:13-14` (`_id` → `id`)
- Modify: `app/Http/Controllers/AdminController.php` (`query_form_view`, `products_store` price, minor)

**Interfaces:**
- Consumes: models from Task 7. Pagination/`where`/`orderBy` calls in AdminController are driver-agnostic and stay as-is.

- [ ] **Step 1: Fix base Controller**

In `app/Http/Controllers/Controller.php` replace:

```php
$this->user_id = auth()->user()->_id;
$this->user = User::where('_id', $this->user_id)->first();
```
with:

```php
$this->user_id = auth()->id();
$this->user = auth()->user();
```

- [ ] **Step 2: AdminController fixes**

In `app/Http/Controllers/AdminController.php`:

1. `query_form_view` (line ~327): replace

```php
$query_form = QueryForm::where("_id", $id)->first();
```
with

```php
$query_form = QueryForm::findOrFail($id);
```

2. `products_store` (line ~182) and `products_update` (line ~231): price is now DECIMAL — stop appending the `' USD'` suffix. Replace both occurrences of

```php
'price' => $validatedData['price'] . ' USD',
```
and
```php
$product->price = $validatedData['price'] . ' USD';
```
with
```php
'price' => $validatedData['price'],
```
and
```php
$product->price = $validatedData['price'];
```

3. `products_store`/`products_update` receive `category_id`/`make_id` from the admin UI — after Task 11 the Vue selects submit integer ids, so validation `'category_id' => 'required'` still holds. Tighten to `'required|exists:categories,id'` for both `category_id` and `make_id` in both methods.

- [ ] **Step 3: Verify**

Run: `php artisan tinker --execute="app(App\Http\Controllers\AdminController::class); echo 'ok';"` — expected `ok` (class loads).
Run: `grep -n "_id\"" app/Http/Controllers/AdminController.php | grep -v category_id | grep -v make_id` — expected: no `_id` string-key matches remain.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Controller.php app/Http/Controllers/AdminController.php
git commit -m "refactor: admin controller off Mongo _id, decimal price

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 9: DashboardController conversion (aggregations → GROUP BY)

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php` (`home`, `product_category`; rest unchanged)

**Interfaces:**
- Consumes: `products_category_make_idx`, `products_body_style_idx`, `categories_type_created_idx` indexes (Task 6).
- Produces: same Inertia prop shapes as before, except ids are ints and `makes[].category_id` is a stringified int (frontend uses it in URLs — keep as string for parity).

- [ ] **Step 1: Rewrite `home()` data queries**

Replace the two `Products::raw(...)` aggregations and the `$makes` block in `home()` with:

```php
    public function home()
    {
        $productCounts = Products::query()
            ->whereNotNull('make_id')
            ->groupBy('make_id')
            ->selectRaw('make_id, COUNT(*) as count')
            ->pluck('count', 'make_id');

        $makes = Categories::where('type', 'make')
            ->select('id', 'cat_title', 'image')
            ->get()
            ->map(fn ($make) => [
                'category_id'   => (string) $make->id,
                'cat_title'     => $make->cat_title,
                'image'         => $make->image,
                'product_count' => $productCounts[$make->id] ?? 0,
            ]);

        $bodyStyle = Products::query()
            ->whereNotNull('body_style')
            ->groupBy('body_style')
            ->selectRaw('body_style, COUNT(*) as count')
            ->get();

        $products = Products::where('body_style', 'Sedan')
            ->select('title', 'front_image', 'id', 'product_details', 'body_style')
            ->limit(8)
            ->get();

        $country_products = Products::where('country', 'China')
            ->select('title', 'front_image', 'id', 'product_details', 'body_style')
            ->limit(8)
            ->get();

        $blogs = Blogs::orderBy('created_at', 'DESC')->where('publish_status', 'published')->limit(3)->get();

        return Inertia::render('Home', [
            'body_styles' => $bodyStyle,
            'body_styles_products' => $products,
            'country_products' => $country_products,
            'makes' => $makes,
            'blogs' => $blogs,
        ]);
    }
```

- [ ] **Step 2: Rewrite `product_category()`**

```php
    public function product_category($category_id)
    {
        $category = Categories::select('id', 'cat_title', 'image', 'type')
            ->findOrFail($category_id);

        // Per-make counts within this category — covered by products_category_make_idx.
        $productCounts = Products::query()
            ->where('category_id', $category->id)
            ->whereNotNull('make_id')
            ->groupBy('make_id')
            ->selectRaw('make_id, COUNT(*) as count')
            ->pluck('count', 'make_id');

        $totalProductsCount = Products::where('category_id', $category->id)->count();

        $makes = Categories::where('type', 'make')
            ->whereIn('id', $productCounts->keys())
            ->select('id', 'cat_title', 'image')
            ->get()
            ->map(fn ($make) => [
                'category_id'   => (string) $make->id,
                'cat_title'     => $make->cat_title,
                'image'         => $make->image,
                'product_count' => $productCounts[$make->id] ?? 0,
            ]);

        $bodyStyle = Products::query()
            ->where('category_id', $category->id)
            ->whereNotNull('body_style')
            ->groupBy('body_style')
            ->selectRaw('body_style')
            ->get();

        $country_products = Products::where('country', 'China')
            ->where('category_id', $category->id)
            ->select('title', 'front_image', 'id', 'product_details', 'body_style')
            ->limit(8)
            ->get();

        return Inertia::render('ProductCategory', [
            'category'             => $category,
            'makes'                => $makes,
            'body_styles'          => $bodyStyle,
            'total_products_count' => $totalProductsCount,
            'country_products'     => $country_products,
        ]);
    }
```

- [ ] **Step 3: Update the three small filter methods** — in `filter_bodystyle`, `filter_countryproducts`, `filter_countryproduct_category`, change the selected column `"_id"` to `"id"` (three `->select(...)` calls).

- [ ] **Step 4: Verify with tinker**

```bash
php artisan tinker --execute="request()->merge([]); $r = app(App\Http\Controllers\DashboardController::class)->filter_bodystyle(); echo $r->status();"
```
Expected: `200`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/DashboardController.php
git commit -m "refactor: dashboard aggregations to SQL GROUP BY

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 10: ShopController conversion (the heavy one)

**Files:**
- Modify: `app/Http/Controllers/ShopController.php` (all four methods)

**Interfaces:**
- Consumes: `Products::scopeSearch` (Task 7), indexes (Task 6).
- Produces: identical Inertia/JSON prop shapes with `id` instead of `_id`; `product_detail` accepts both numeric id and legacy 24-hex `mongo_id`.

- [ ] **Step 1: Rewrite `home()`** — the `$lookup` pipeline becomes one JOIN + GROUP BY (inner join drops empty categories, matching the old `products != []` stage):

```php
    public function home()
    {
        $data = Cache::remember('shop_home_data', 60, function () {
            $categories = Categories::query()
                ->join('products', 'products.category_id', '=', 'categories.id')
                ->where('categories.type', 'category')
                ->groupBy('categories.id', 'categories.cat_title', 'categories.image', 'categories.created_at')
                ->orderByDesc('categories.created_at')
                ->selectRaw('categories.id, categories.cat_title, categories.image, COUNT(products.id) as products_count')
                ->get()
                ->toArray();

            $makes = Categories::where('type', 'make')
                ->orderBy('created_at', 'desc')
                ->select('id', 'cat_title', 'image')
                ->get()
                ->toArray();

            return [
                'categories' => $categories,
                'makes' => $makes,
            ];
        });

        $products = $this->listing();

        return Inertia::render('Shop', [
            'products' => $products,
            'categories' => $data['categories'],
            'makes' => $data['makes'],
        ]);
    }
```

- [ ] **Step 2: Rewrite `listing()`** — replace every `'regex'` operator; keep the filter semantics:

```php
    public function listing()
    {
        $request = request();
        $query = Products::with(['category', 'make']);

        $type = $request->input('type') ?? null;

        if ($type == 'search') {
            $filter_data = $request->all();

            $search = $filter_data['search'] ?? null;
            $makeId = $filter_data['make'] ?? null;
            $bodyStyle = $filter_data['body_style'] ?? null;

            if ($search) {
                $query->search($search);
            }

            if ($makeId) {
                $query->where('make_id', $makeId);
            }

            if ($bodyStyle) {
                $query->where('body_style', $bodyStyle);
            }

            $priceMin = $filter_data['price_min'] ?? null;
            $priceMax = $filter_data['price_max'] ?? null;

            if ($priceMin && $priceMax) {
                $query->whereBetween('price', [(int) $priceMin, (int) $priceMax]);
            } elseif ($priceMin) {
                $query->where('price', '>=', (int) $priceMin);
            } elseif ($priceMax) {
                $query->where('price', '<=', (int) $priceMax);
            }

            $yearFrom = $filter_data['year_from'] ?? null;
            $yearTo = $filter_data['year_to'] ?? null;

            if ($yearFrom || $yearTo) {
                $yearPatterns = [];

                if ($yearFrom && $yearTo) {
                    $minYear = min((int) $yearFrom, (int) $yearTo);
                    $maxYear = max((int) $yearFrom, (int) $yearTo);
                    $yearPatterns = range($minYear, $maxYear);
                } elseif ($yearFrom) {
                    $yearPatterns = range((int) $yearFrom, (int) $yearFrom + 4);
                } elseif ($yearTo) {
                    $yearPatterns = range((int) $yearTo - 4, (int) $yearTo);
                }

                if (! empty($yearPatterns)) {
                    $yearRegex = implode('|', $yearPatterns);
                    $query->whereRaw('product_details REGEXP ?', ["\\\\b({$yearRegex})\\\\b"]);
                }
            }

            $mileageMin = $filter_data['mileage_min'] ?? null;
            $mileageMax = $filter_data['mileage_max'] ?? null;

            if ($mileageMin || $mileageMax) {
                $query->where('product_details', 'like', '%<strong>Mileage:</strong>%');
            }
        } else {
            $categoryFilters = $request->filled('category') ? explode(',', $request->input('category')) : [];
            $makeFilters = $request->filled('make') ? explode(',', $request->input('make')) : [];
            $countryFilters = $request->filled('country') ? explode(',', $request->input('country')) : [];
            $priceFilter = $request->input('price');
            $bodyStyle = $request->input('body_style');
            $search = $request->input('search');

            $query = $query->when($search, fn ($q) => $q->search($search))
                ->when(!empty($categoryFilters), fn($q) => $q->whereIn('category_id', $categoryFilters))
                ->when(!empty($makeFilters), fn($q) => $q->whereIn('make_id', $makeFilters))
                ->when($bodyStyle, fn($q) => $q->where('body_style', $bodyStyle))
                ->when(!empty($countryFilters), fn($q) => $q->whereIn('country', $countryFilters))
                ->when($priceFilter, function ($q) use ($priceFilter) {
                    switch ($priceFilter) {
                        case 'under-500':
                            $q->where('price', '<', 500);
                            break;
                        case '500-1000':
                            $q->whereBetween('price', [500, 1000]);
                            break;
                        case '1000-2000':
                            $q->whereBetween('price', [1000, 2000]);
                            break;
                        case '2000-5000':
                            $q->whereBetween('price', [2000, 5000]);
                            break;
                        case '5000-10000':
                            $q->whereBetween('price', [5000, 10000]);
                            break;
                        case '10000-20000':
                            $q->whereBetween('price', [10000, 20000]);
                            break;
                        case 'over-20000':
                            $q->where('price', '>', 20000);
                            break;
                    }
                });
        }
        $results = $query->orderByDesc('created_at')->paginate(30);

        return $results;
    }
```
Note on the REGEXP binding: PHP string `"\\\\b"` sends `\\b` to MariaDB, which its PCRE engine reads as the word-boundary escape. Verify in Step 6.

- [ ] **Step 3: Rewrite `product_detail()` + `filter_country_products()`** — `$sample` becomes a covering-index random pick (`pluck('id')` + `inRandomOrder` on the filtered subset), and legacy ObjectId URLs keep working:

```php
    public function product_detail($id)
    {
        $product_detail = Products::with('category')
            ->when(
                ctype_digit((string) $id),
                fn ($q) => $q->where('id', $id),
                fn ($q) => $q->where('mongo_id', $id) // legacy Mongo URLs
            )
            ->first();

        if (!$product_detail) {
            abort(404);
        }

        $similar_products = $this->randomSimilarProducts($product_detail->category_id, 'China', $product_detail->id);

        return Inertia::render('ProductDetail', [
            "product_detail" => $product_detail,
            "similar_products" => $similar_products,
        ]);
    }

    public function filter_country_products(Request $request)
    {
        $country = $request->input('country', 'China');
        $product_id = $request->input('product_id');

        $product = Products::find($product_id);

        if (!$product) {
            return response()->json(['similar_products' => []]);
        }

        return response()->json([
            'similar_products' => $this->randomSimilarProducts($product->category_id, $country, $product->id),
        ]);
    }

    /**
     * Random N products in the same category+country. Two-step: pick ids via
     * the (category_id, country) covering index, then hydrate — avoids
     * ORDER BY RAND() over full rows.
     */
    private function randomSimilarProducts(?int $categoryId, string $country, int $excludeId, int $limit = 4)
    {
        if ($categoryId === null) {
            return collect();
        }

        $randomIds = Products::query()
            ->where('category_id', $categoryId)
            ->where('country', $country)
            ->where('id', '!=', $excludeId)
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('id');

        return Products::whereIn('id', $randomIds)->with('category')->get();
    }
```
(The old code cast `_id` to string; ints serialize fine — Vue templates only render/route with them.)

- [ ] **Step 4: Rewrite `search_products()`** — `$regexp` + `$lookup` pipeline becomes an eager-loaded prefix search (drop the nonexistent `make_title` field the old pipeline referenced):

```php
    public function search_products(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Products::query()
            ->with('make:id,cat_title')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('country', 'like', "%{$query}%");
            })
            ->select('id', 'title', 'front_image', 'make_id', 'country', 'price')
            ->limit(10)
            ->get();

        $results = $products->map(fn ($product) => [
            'id' => $product->id,
            'title' => $product->title ?? '',
            'front_image' => $product->front_image ?? '',
            'make' => $product->make ? ['cat_title' => $product->make->cat_title] : null,
            'country' => $product->country ?? '',
            'price' => $product->price ?? 0,
        ])->values();

        return response()->json($results);
    }
```

- [ ] **Step 5: Clear stale cache** — the old Mongo-shaped `shop_home_data` may be serialized in the `cache` table:

Run: `php artisan cache:clear`

- [ ] **Step 6: Verify via tinker**

```bash
php artisan tinker --execute="
request()->merge(['search' => 'shacman']);
echo app(App\Http\Controllers\ShopController::class)->listing()->total() . PHP_EOL;
echo App\Models\Products::whereRaw('product_details REGEXP ?', ['\\\\\\\\b(2017|2018)\\\\\\\\b'])->limit(1)->count() . PHP_EOL;
"
```
Expected: non-zero total; regexp query returns 1 without error. (Quoting through tinker is fiddly — a scratch PHP file via `php artisan tinker < file` is an acceptable substitute.)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ShopController.php
git commit -m "refactor: shop queries from Mongo aggregations to indexed SQL

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 11: Frontend `_id` → `id` + build

**Files:**
- Modify: `resources/js/pages/Home.vue` (7 refs), `resources/js/pages/Shop.vue` (7 refs), `resources/js/pages/Admin/Products/Create.vue` (16 refs), `resources/js/pages/Admin/Products/Edit.vue` (16 refs)

**Interfaces:**
- Consumes: controllers now emit `id` (int). All `:key="x._id"`, `route(..., { category: category._id })`, `category._id` option values, etc. become `.id`.

- [ ] **Step 1: Replace `_id` with `id`** in the four files. Mechanical rename — every `._id` becomes `.id` and every `'_id'`/`"_id"` key becomes `'id'`. Careful NOT to touch `category_id` / `make_id` / `product_id` occurrences (they keep their names). Safe regex: replace `(?<![a-z_])_id\b` → `id` per file, then eyeball the diff.

- [ ] **Step 2: Verify no stragglers**

Run: `grep -rn "_id" resources/js/pages/ | grep -v category_id | grep -v make_id | grep -v product_id`
Expected: no matches.

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: Vite build succeeds with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js
git commit -m "refactor: frontend uses SQL id instead of Mongo _id

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 12: Remove MongoDB package + config cleanup

**Files:**
- Modify: `composer.json` (drop `mongodb/laravel-mongodb`), `config/database.php` (drop `mongodb` connection block), `.env` (remove `MONGODB_URI`, `MONGODB_DATABASE`)
- Modify: `app/Http/Controllers/GoogleAuthController.php` (delete the dead commented `data_upload` bodies — they reference Mongo-era `$category->_id`; keep the method returning its JSON message, or delete method + its `/data-upload` route in `routes/web.php:46`. Prefer deleting both — dead scraping code.)

**Interfaces:**
- Produces: project boots with zero Mongo references.

- [ ] **Step 1: Sanity grep** — confirm nothing still imports Mongo:

Run: `grep -rn "MongoDB\|mongodb" app/ routes/ bootstrap/ database/ --include=*.php`
Expected: no matches (config/ checked separately next).

- [ ] **Step 2: Remove the package**

```bash
composer remove mongodb/laravel-mongodb
```
Expected: composer resolves and regenerates autoload without error.

- [ ] **Step 3: Delete the `mongodb` connection block** from `config/database.php` (lines 33–37) and remove `MONGODB_URI` / `MONGODB_DATABASE` from `.env`.

- [ ] **Step 4: Remove dead code** — in `routes/web.php` delete line 46 (`Route::get('/data-upload', ...)`), and in `GoogleAuthController.php` delete `data_upload()`, the commented duplicate, `formatProductDetailsAsHtml()`, and `saveImage()` (all dead/Mongo-era).

- [ ] **Step 5: Verify boot + tests**

```bash
php artisan config:clear && php artisan route:list --columns=method,uri | head -30
php artisan test
```
Expected: routes list without error; test suite passes (starter-kit auth feature tests now run against sqlite with the SQL User model — they should pass; if any test references dropped columns, fix the test, not the schema).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: remove mongodb package, config and dead code

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 13: End-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Serve and smoke-test every converted route**

```bash
php artisan serve --port=8000 &
```
Then request each and expect HTTP 200 with sensible content:

```bash
curl -s -o /dev/null -w "%{http_code} /\n"                    http://127.0.0.1:8000/
curl -s -o /dev/null -w "%{http_code} /inventory\n"           http://127.0.0.1:8000/inventory
curl -s -o /dev/null -w "%{http_code} listing search\n"       "http://127.0.0.1:8000/inventory/listing?type=search&search=truck&price_min=1000&price_max=50000&year_from=2015&year_to=2020"
curl -s -o /dev/null -w "%{http_code} search json\n"          "http://127.0.0.1:8000/search/products?q=shacman"
curl -s -o /dev/null -w "%{http_code} blogs\n"                http://127.0.0.1:8000/blogs
curl -s -o /dev/null -w "%{http_code} category\n"             http://127.0.0.1:8000/category/1
curl -s -o /dev/null -w "%{http_code} filter-bodystyle\n"     "http://127.0.0.1:8000/filter-bodystyle?body_style=Sedan"
```
Also pick a real product id (`php artisan tinker --execute="echo App\Models\Products::first()->id;"`) and a real `mongo_id`, then verify BOTH detail URLs return 200:
`/inventory/product-detail/<id>` and `/inventory/product-detail/<mongo_id>`.

- [ ] **Step 2: Verify query plans use indexes**

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root supreme_motors -e "EXPLAIN SELECT make_id, COUNT(*) FROM products WHERE make_id IS NOT NULL GROUP BY make_id; EXPLAIN SELECT id FROM products WHERE category_id=1 AND country='China' ORDER BY RAND() LIMIT 4;"
```
Expected: first uses `products_category_make_idx` or `make_id` index (`Using index`); second shows `products_category_country_idx`.

- [ ] **Step 3: POST-route spot checks** — newsletter duplicate guard + query form:

```bash
curl -s -X POST http://127.0.0.1:8000/newsletter/subscribe -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"email\":\"ali331358@gmail.com\"}" -w "\n%{http_code}\n"
```
Expected: `422` with "already subscribed" (email exists from import). (CSRF: if 419, fetch the cookie/token first or verify via tinker instead — the goal is confirming the unique-email path against imported data.)

- [ ] **Step 4: Final test run + commit any fixes**

```bash
php artisan test
git status
```
Expected: all green, clean tree.

---

## Self-Review (completed)

- **Coverage:** data import (Tasks 2,4,5), all 7 models (7), all controllers touching Mongo — base (8), Admin (8), Dashboard (9), Shop (10), GoogleAuth dead code (12), frontend `_id` (11), package/config removal (12), optimization via deferred indexes + FULLTEXT + covering-index random picks + JOIN/GROUP BY rewrites (6,9,10), sessions/cache intentionally not imported (Global Constraints).
- **Type consistency:** `scopeSearch` defined Task 7, consumed Task 10 as `->search()`; `randomSimilarProducts(?int, string, int, int)` defined and used only in Task 10; `mongo_id` written by Task 4/5, read by Task 10 fallback; index names in Task 6 match the EXPLAIN expectations in Task 13.
- **Known judgment calls:** price `""` → `0` (not NULL) so Vue `.toLocaleString()` never crashes; `make_title` search field dropped (never existed in data); inner-JOIN drops product-less categories exactly like the old `$ne: []` stage.
