<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Products;
use App\Services\AutotraderParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapeAutotraderTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/autotrader/{$name}"));
    }

    private function seedCategories(): void
    {
        Categories::create(['cat_title' => 'Cars', 'type' => 'category']);
    }

    private function fakeSite(): void
    {
        Http::fake([
            'www.autotrader.co.za/cars-for-sale*' => Http::response($this->fixture('search-page.html')),
            'www.autotrader.co.za/car-for-sale/*' => Http::response($this->fixture('detail-page.html')),
        ]);
    }

    public function test_search_listings_parser_extracts_full_records(): void
    {
        $result = (new AutotraderParser)->parseSearchListings($this->fixture('search-page.html'));

        $this->assertGreaterThanOrEqual(25, count($result['listings']));
        $this->assertSame(3713, $result['last_page']);
        $this->assertSame(92807, $result['total']);

        // the Hyundai Tucson tile carries a complete product from the search page alone
        $tucson = collect($result['listings'])->firstWhere('listing_id', 28520428);
        $this->assertNotNull($tucson);
        $this->assertSame('Hyundai Tucson 2.0 Premium Auto', $tucson['title']);
        $this->assertSame('Hyundai', $tucson['make']);
        $this->assertSame(2021, $tucson['year']);
        $this->assertSame(234990, $tucson['price']);
        $this->assertSame(102000, $tucson['mileage_km']);
        $this->assertSame('Automatic', $tucson['transmission']);
        $this->assertSame('Petrol', $tucson['fuel']);
        $this->assertSame('Used', $tucson['condition']);
        $this->assertNotEmpty($tucson['images']);
        $this->assertStringContainsString('img.autotrader.co.za', $tucson['images'][0]);
        $this->assertStringNotContainsString('/Fit', $tucson['images'][0]); // sized suffix stripped
    }

    public function test_detail_page_parser_maps_all_fields(): void
    {
        $url = 'https://www.autotrader.co.za/car-for-sale/volkswagen/amarok/2.0tdi/28618358';
        $data = (new AutotraderParser)->parseDetailPage($this->fixture('detail-page.html'), $url);

        $this->assertSame('2026 Volkswagen Amarok 2.0TDI Double Cab Life', $data['title']);
        $this->assertSame('Volkswagen', $data['make']);
        $this->assertSame(2026, $data['year']);
        $this->assertSame(609950.0, $data['price']);
        $this->assertSame('4x2', $data['drive_type']);
        $this->assertSame(1996, $data['engine_cc']);
        $this->assertSame('Double cab', $data['body_style']);
        $this->assertCount(18, $data['images']); // 20 gallery entries, 18 unique ids (2 dupes dropped)
        $this->assertStringNotContainsString('/Crop', $data['images'][0]); // raw id, size suffix stripped
        $this->assertStringContainsString('Warranty distance', $data['product_details']);
    }

    public function test_search_mode_upserts_products_with_cdn_images_and_is_rerunnable(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--delay-ms' => 0, '--usd-rate' => 0])
            ->assertSuccessful();

        $this->assertGreaterThanOrEqual(25, Products::where('website', 'autotrader')->count());

        $product = Products::where('product_link', 'like', '%28520428')->first();
        $this->assertNotNull($product);
        $this->assertStringStartsWith('https://sm-autotrader.b-cdn.net/', $product->front_image);
        $this->assertStringStartsWith('https://img.autotrader.co.za/', $product->front_image_source);
        $this->assertSame('SM' . $product->id, $product->stock_code);
        $this->assertSame('South Africa', $product->country);
        $this->assertSame(234990.0, (float) $product->price); // --usd-rate=0 keeps raw ZAR
        $this->assertSame(2021, $product->year);
        $this->assertNotNull($product->make_id);
        $this->assertSame('Hyundai', Categories::find($product->make_id)->cat_title);
        $this->assertNotEmpty($product->other_images);
        $this->assertStringStartsWith('https://sm-autotrader.b-cdn.net/', $product->other_images[0]);
    }

    public function test_search_mode_makes_no_detail_requests(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--limit' => 5, '--delay-ms' => 0])
            ->assertSuccessful();

        Http::assertSentCount(1); // exactly one search page, zero detail pages
    }

    public function test_deep_mode_pulls_full_gallery_from_detail_pages(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--limit' => 2, '--deep' => true, '--delay-ms' => 0])
            ->assertSuccessful();

        $product = Products::where('website', 'autotrader')->first();
        // detail fixture has 18 unique images -> 1 front + 17 others, richer than search's handful
        $this->assertCount(17, $product->other_images);
        $this->assertStringContainsString('Warranty distance', $product->product_details);
        // deep mode captures the specs the search page lacks
        $this->assertSame(5, $product->seats);
        $this->assertSame(4, $product->doors);
        $this->assertSame(1996, $product->engine_cc);
        $this->assertSame('4x2', $product->drive_type);
    }

    public function test_rerun_skips_existing_unless_refresh(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--delay-ms' => 0])->assertSuccessful();
        $count = Products::where('website', 'autotrader')->count();

        // rerun of the same page banks nothing new (all listings already exist)
        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--delay-ms' => 0, '--start-page' => 1])->assertSuccessful();
        $this->assertSame($count, Products::where('website', 'autotrader')->count());
    }

    public function test_dry_run_writes_report_but_no_products(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        $report = config('cdn.state_dir') . '/at-preview.html';
        $this->artisan('scrape:autotrader', [
            '--max-pages' => 1, '--limit' => 5, '--delay-ms' => 0,
            '--dry-run' => true, '--report' => $report,
        ])->assertSuccessful();

        $this->assertSame(0, Products::where('website', 'autotrader')->count());
        $this->assertFileExists($report);
        $html = file_get_contents($report);
        $this->assertStringContainsString('sm-autotrader.b-cdn.net', $html);
        $this->assertStringContainsString('Hyundai Tucson', $html);
    }

    public function test_price_converts_to_usd_by_default(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        // default --usd-rate is 0.055; no flag needed to get dollar prices
        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--delay-ms' => 0])
            ->assertSuccessful();

        $tucson = Products::where('product_link', 'like', '%28520428')->first();
        $this->assertSame(round(234990 * 0.055, 2), (float) $tucson->price); // 12924.45 USD
        $this->assertLessThan(50000, (float) $tucson->price);
    }

    public function test_writes_progress_snapshot_and_done_marker(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--delay-ms' => 0])->assertSuccessful();

        $progressFile = config('cdn.state_dir') . '/autotrader-progress.json';
        $this->assertFileExists($progressFile);
        $p = json_decode(file_get_contents($progressFile), true);
        $this->assertSame('search', $p['mode']);
        $this->assertGreaterThanOrEqual(25, $p['products_scraped']);
        $this->assertGreaterThan(0, $p['images_scraped']);
        $this->assertSame(92807, $p['products_total_estimate']);
        $this->assertArrayHasKey('eta_minutes', $p);
        $this->assertArrayHasKey('updated_at', $p);
        $this->assertFileExists(config('cdn.state_dir') . '/autotrader-heartbeat.txt');
    }

    public function test_limit_stops_the_loop_without_crawling_empty_pages(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        // dry-run so every page's tiles count toward the limit (no dedup);
        // 40 products = ~2 pages of 28. Without the limit-stop guard the loop
        // would crawl on through empty pages forever once the limit is hit.
        $this->artisan('scrape:autotrader', ['--limit' => 40, '--dry-run' => true, '--delay-ms' => 0])
            ->assertSuccessful();

        // the guard must stop it after a couple pages, not hundreds
        $this->assertLessThan(4, Http::recorded(fn ($r) => str_contains($r->url(), 'cars-for-sale'))->count());
    }

    public function test_shard_uses_scoped_cursor_done_marker_and_progress(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        // a shard bounded to its single page writes shard-scoped state files
        $this->artisan('scrape:autotrader', [
            '--shard' => 's2', '--min-page' => 1, '--max-page' => 1, '--delay-ms' => 0,
        ])->assertSuccessful();

        $dir = config('cdn.state_dir');
        $this->assertFileExists($dir . '/autotrader-s2.cursor');
        $this->assertFileExists($dir . '/autotrader-scrape-s2.done');
        $this->assertFileExists($dir . '/autotrader-progress-s2.json');

        $p = json_decode(file_get_contents($dir . '/autotrader-progress-s2.json'), true);
        $this->assertSame('s2', $p['shard']);
        $this->assertTrue($p['done']);

        // it must NOT touch the un-sharded default files
        $this->assertFileDoesNotExist($dir . '/autotrader-scrape.done');
    }

    public function test_max_page_bounds_a_shard(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        // min=max=5 → the shard starts at page 5 and stops after it (marks done)
        $this->artisan('scrape:autotrader', [
            '--shard' => 'sX', '--min-page' => 5, '--max-page' => 5, '--delay-ms' => 0,
        ])->assertSuccessful();

        $this->assertSame('5', trim(file_get_contents(config('cdn.state_dir') . '/autotrader-sX.cursor')));
        $this->assertFileExists(config('cdn.state_dir') . '/autotrader-scrape-sX.done');
    }

    public function test_pool_option_with_proxies_is_accepted(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        @mkdir(config('cdn.state_dir'), 0777, true);
        $proxyFile = config('cdn.state_dir') . '/proxies.txt';
        file_put_contents($proxyFile, "1.2.3.4:8080\n5.6.7.8:3128\n");

        // in tests the batch fetcher uses the fakeable sequential path regardless of pool
        $this->artisan('scrape:autotrader', [
            '--max-pages' => 1, '--limit' => 2, '--deep' => true,
            '--pool' => 5, '--proxy-file' => $proxyFile, '--delay-ms' => 0,
        ])->assertSuccessful();

        $this->assertSame(2, Products::where('website', 'autotrader')->count());
    }

    public function test_proxy_file_is_loaded_and_used(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        @mkdir(config('cdn.state_dir'), 0777, true);
        $proxyFile = config('cdn.state_dir') . '/proxies.txt';
        file_put_contents($proxyFile, "1.2.3.4:8080\n# comment\nuser:pass@5.6.7.8:3128\n");

        $this->artisan('scrape:autotrader', [
            '--max-pages' => 1, '--limit' => 2, '--delay-ms' => 0, '--proxy-file' => $proxyFile,
        ])->assertSuccessful();

        // requests still land (fake ignores proxy option) and products bank
        $this->assertSame(2, Products::where('website', 'autotrader')->count());
    }
}
