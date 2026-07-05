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

    public function test_search_page_parser_extracts_listing_urls_and_last_page(): void
    {
        $result = (new AutotraderParser)->parseSearchPage($this->fixture('search-page.html'));

        $this->assertGreaterThanOrEqual(15, count($result['listing_urls']));
        $this->assertContains('https://www.autotrader.co.za/car-for-sale/volkswagen/amarok/2.0tdi/28618358', $result['listing_urls']);
        $this->assertSame(3713, $result['last_page']);
    }

    public function test_detail_page_parser_maps_all_fields(): void
    {
        $url = 'https://www.autotrader.co.za/car-for-sale/volkswagen/amarok/2.0tdi/28618358';
        $data = (new AutotraderParser)->parseDetailPage($this->fixture('detail-page.html'), $url);

        $this->assertSame('2026 Volkswagen Amarok 2.0TDI Double Cab Life', $data['title']);
        $this->assertSame('Volkswagen', $data['make']);
        $this->assertSame('Amarok', $data['model']);
        $this->assertSame(2026, $data['year']);
        $this->assertSame(609950.0, $data['price']);
        $this->assertSame('Diesel', $data['fuel']);
        $this->assertSame('Automatic', $data['transmission']);
        $this->assertSame('New', $data['condition']);
        $this->assertSame('Right', $data['steering']);
        $this->assertSame('4x2', $data['drive_type']);
        $this->assertSame(1996, $data['engine_cc']);
        $this->assertSame('Double cab', $data['body_style']);
        $this->assertSame('South Africa', $data['country']);
        $this->assertSame(28618358, $data['listing_id']);
        $this->assertCount(20, $data['images']);
        $this->assertStringContainsString('img.autotrader.co.za', $data['images'][0]);
        $this->assertStringContainsString('<ul>', $data['product_details']);
        $this->assertStringContainsString('Warranty distance', $data['product_details']);
        $this->assertNull($data['mileage_km']); // new car — no mileage icon
    }

    public function test_scrape_upserts_products_with_cdn_images_and_is_rerunnable(): void
    {
        $this->seedCategories();
        Http::fake([
            'www.autotrader.co.za/cars-for-sale*' => Http::response($this->fixture('search-page.html')),
            'www.autotrader.co.za/car-for-sale/*' => Http::response($this->fixture('detail-page.html')),
        ]);

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--limit' => 3, '--delay-ms' => 0])
            ->assertSuccessful();

        $this->assertSame(3, Products::where('website', 'autotrader')->count());

        $product = Products::where('website', 'autotrader')->first();
        $this->assertStringStartsWith('https://sm-autotrader.b-cdn.net/', $product->front_image);
        $this->assertStringStartsWith('https://img.autotrader.co.za/', $product->front_image_source);
        $this->assertSame('SM' . $product->id, $product->stock_code);
        $this->assertSame('South Africa', $product->country);
        $this->assertSame(609950.0, (float) $product->price);
        $this->assertNotNull($product->make_id);
        $this->assertSame('Volkswagen', Categories::find($product->make_id)->cat_title);
        $this->assertCount(19, $product->other_images);
        $this->assertStringStartsWith('https://sm-autotrader.b-cdn.net/', $product->other_images[0]);
        $this->assertCount(19, json_decode($product->other_images_source, true));

        // rerun: same listings upsert in place, no duplicates
        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--limit' => 3, '--delay-ms' => 0, '--start-page' => 1, '--refresh' => true])
            ->assertSuccessful();
        $this->assertSame(3, Products::where('website', 'autotrader')->count());
    }

    public function test_scrape_resumes_from_cursor_and_skips_existing(): void
    {
        $this->seedCategories();
        Http::fake([
            'www.autotrader.co.za/cars-for-sale*' => Http::response($this->fixture('search-page.html')),
            'www.autotrader.co.za/car-for-sale/*' => Http::response($this->fixture('detail-page.html')),
        ]);

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--limit' => 2, '--delay-ms' => 0])
            ->assertSuccessful();

        // cursor checkpoint exists only after a fully completed page; limit cut
        // the page short, so a rerun re-reads page 1 but skips banked listings
        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--limit' => 2, '--delay-ms' => 0])
            ->assertSuccessful();

        $this->assertSame(4, Products::where('website', 'autotrader')->count());
    }

    public function test_dry_run_writes_report_but_no_products(): void
    {
        $this->seedCategories();
        Http::fake([
            'www.autotrader.co.za/cars-for-sale*' => Http::response($this->fixture('search-page.html')),
            'www.autotrader.co.za/car-for-sale/*' => Http::response($this->fixture('detail-page.html')),
        ]);

        $report = config('cdn.state_dir') . '/at-preview.html';
        $this->artisan('scrape:autotrader', [
            '--max-pages' => 1, '--limit' => 2, '--delay-ms' => 0,
            '--dry-run' => true, '--report' => $report,
        ])->assertSuccessful();

        $this->assertSame(0, Products::where('website', 'autotrader')->count());
        $this->assertFileExists($report);
        $html = file_get_contents($report);
        $this->assertStringContainsString('sm-autotrader.b-cdn.net', $html);
        $this->assertStringContainsString('2026 Volkswagen Amarok', $html);
    }

    public function test_withdrawn_listing_is_skipped_not_fatal(): void
    {
        $this->seedCategories();
        Http::fake([
            'www.autotrader.co.za/cars-for-sale*' => Http::response($this->fixture('search-page.html')),
            'www.autotrader.co.za/car-for-sale/*' => Http::response('gone', 404),
        ]);

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--limit' => 2, '--delay-ms' => 0])
            ->assertSuccessful();

        $this->assertSame(0, Products::where('website', 'autotrader')->count());
        $this->assertFileExists(config('cdn.state_dir') . '/autotrader-failures.log');
    }

    public function test_usd_rate_converts_price(): void
    {
        $this->seedCategories();
        Http::fake([
            'www.autotrader.co.za/cars-for-sale*' => Http::response($this->fixture('search-page.html')),
            'www.autotrader.co.za/car-for-sale/*' => Http::response($this->fixture('detail-page.html')),
        ]);

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--limit' => 1, '--delay-ms' => 0, '--usd-rate' => 0.055])
            ->assertSuccessful();

        $this->assertSame(33547.25, (float) Products::where('website', 'autotrader')->first()->price);
    }
}
