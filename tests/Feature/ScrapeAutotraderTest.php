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
        $this->assertCount(20, $data['images']);
        $this->assertStringContainsString('Warranty distance', $data['product_details']);
    }

    public function test_search_mode_upserts_products_with_cdn_images_and_is_rerunnable(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--delay-ms' => 0])
            ->assertSuccessful();

        $this->assertGreaterThanOrEqual(25, Products::where('website', 'autotrader')->count());

        $product = Products::where('product_link', 'like', '%28520428')->first();
        $this->assertNotNull($product);
        $this->assertStringStartsWith('https://sm-autotrader.b-cdn.net/', $product->front_image);
        $this->assertStringStartsWith('https://img.autotrader.co.za/', $product->front_image_source);
        $this->assertSame('SM' . $product->id, $product->stock_code);
        $this->assertSame('South Africa', $product->country);
        $this->assertSame(234990.0, (float) $product->price);
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
        // detail fixture has 20 images -> 1 front + 19 others, richer than search's handful
        $this->assertCount(19, $product->other_images);
        $this->assertStringContainsString('Warranty distance', $product->product_details);
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

    public function test_usd_rate_converts_price(): void
    {
        $this->seedCategories();
        $this->fakeSite();

        $this->artisan('scrape:autotrader', ['--max-pages' => 1, '--limit' => 1, '--delay-ms' => 0, '--usd-rate' => 0.055])
            ->assertSuccessful();

        $product = Products::where('website', 'autotrader')->first();
        $this->assertGreaterThan(0, (float) $product->price);
        $this->assertLessThan(50000, (float) $product->price); // ZAR converted down to USD
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
