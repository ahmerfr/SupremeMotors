<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Products;
use App\Services\PerfectMotorsParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapePerfectMotorsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/perfectmotors/{$name}"));
    }

    private function seedCategories(): void
    {
        Categories::create(['cat_title' => 'Cars', 'type' => 'category']);
    }

    /**
     * Fake the origin: a small window of productDetail ids answer 200 with the
     * real car fixture, every other id answers 500 (deleted / never-used id).
     */
    private function fakeSite(array $liveIds = [64000, 64001, 64002]): void
    {
        $car = $this->fixture('detail-page.html');
        $routes = [];
        foreach ($liveIds as $id) {
            $routes["perfect-motors.com/productDetail/{$id}"] = Http::response($car, 200);
        }
        // any other productDetail id -> 500 (not a car)
        $routes['perfect-motors.com/productDetail/*'] = Http::response('Server Error', 500);
        Http::fake($routes);
    }

    public function test_sold_reserved_cars_are_skipped(): void
    {
        $this->seedCategories();
        // a sold/reserved car lists no price ($0.00); an available one has a price
        $sold = '<h1 class="protitle">2021 Toyota Corolla</h1>'
            . '<div class="propricemaincon" id="propricemaincon"><h6>Price</h6><h6>$0.00</h6></div>'
            . '<div class="thumb-gallery-detail"><img src="https://perfect-motors.com/admin-assets/images/1_thumb.jpg"><div class="thumb-gallery-thumbs">';
        $available = '<h1 class="protitle">2019 Honda Civic</h1>'
            . '<div class="propricemaincon" id="propricemaincon"><h6>Price</h6><h6>$12,500.00</h6></div>'
            . '<div class="thumb-gallery-detail"><img src="https://perfect-motors.com/admin-assets/images/2_thumb.jpg"><div class="thumb-gallery-thumbs">';

        Http::fake([
            'perfect-motors.com/productDetail/64000' => Http::response($sold, 200),
            'perfect-motors.com/productDetail/64001' => Http::response($available, 200),
            'perfect-motors.com/productDetail/*' => Http::response('Server Error', 500),
        ]);

        $this->artisan('scrape:perfectmotors', ['--min-id' => 64000, '--max-id' => 64001, '--pool' => 1])
            ->assertSuccessful();

        // only the priced car is banked; the $0.00 sold one is skipped
        $this->assertSame(1, Products::where('website', 'perfectmotors')->count());
        $this->assertNotNull(Products::where('title', 'like', '%Honda Civic%')->first());
        $this->assertNull(Products::where('title', 'like', '%Corolla%')->first());
    }

    public function test_gallery_captures_both_jpg_and_jpeg_extensions(): void
    {
        // newer stock uses _thumb.jpg, older _thumb.jpeg — both must be captured,
        // with the real extension preserved (regression: .jpg cars got 0 images)
        $html = '<h1 class="protitle">2020 Toyota Rush</h1>'
            . '<div class="thumb-gallery-detail owl-carousel">'
            . '<img src="https://perfect-motors.com/admin-assets/images/111_thumb.jpg">'
            . '<img src="https://perfect-motors.com/admin-assets/images/222_thumb.jpeg">'
            . '<img src="https://perfect-motors.com/admin-assets/images/111_thumb.jpg">'
            . '<div class="thumb-gallery-thumbs">'
            . '<img src="https://perfect-motors.com/admin-assets/images/999_thumb.jpg">' // thumbs strip — excluded
            . '</div></div>';

        $data = (new PerfectMotorsParser)->parseDetailPage($html, 'https://perfect-motors.com/productDetail/1');

        $this->assertSame([
            'https://perfect-motors.com/admin-assets/images/111_thumb.jpg',
            'https://perfect-motors.com/admin-assets/images/222_thumb.jpeg',
        ], $data['images']); // .jpg + .jpeg preserved, deduped, thumbs-strip excluded
    }

    public function test_detail_page_parser_maps_all_fields(): void
    {
        $url = 'https://perfect-motors.com/productDetail/70795';
        $data = (new PerfectMotorsParser)->parseDetailPage($this->fixture('detail-page.html'), $url);

        $this->assertSame('1995 Nissan Homy', $data['title']);
        $this->assertSame('Nissan', $data['make']);
        $this->assertSame('Homy', $data['model']);
        $this->assertSame(1995, $data['year']);
        $this->assertSame(5670.0, $data['price']); // USD, stored as-is
        $this->assertSame(234100, $data['mileage_km']);
        $this->assertSame('Automatic', $data['transmission']);
        $this->assertSame('Diesel', $data['fuel']);
        $this->assertSame('Right', $data['steering']); // RHD -> Right
        $this->assertSame(8, $data['seats']);
        $this->assertSame(4, $data['doors']);
        $this->assertSame(2660, $data['engine_cc']);
        $this->assertSame('United Arab Emirates', $data['country']);
        $this->assertSame('Used', $data['condition']);

        // full spec block captured as an array (drives fill-incomplete)
        $this->assertIsArray($data['specifications']);
        $this->assertSame('234,100 Km', $data['specifications']['Milage']);
        $this->assertSame('RHD', $data['specifications']['Steering']);
        $this->assertSame('D6076', $data['specifications']['Stock No.']);
        $this->assertArrayHasKey('Features', $data['specifications']);
        // the commented-out spec table must NOT inject a garbage row
        $this->assertArrayNotHasKey('Mileage-->', $data['specifications']);

        // gallery isolated to THIS car's main carousel only
        $this->assertCount(56, $data['images']);
        $this->assertStringStartsWith('https://perfect-motors.com/admin-assets/images/', $data['images'][0]);
        $this->assertStringContainsString('_thumb.jpeg', $data['images'][0]);
        // a related-car thumb (id 1783007042 from the "Similar Vehicles" block) is excluded
        $this->assertStringNotContainsString('1783007042', implode(',', $data['images']));
        // no duplicate image ids
        $this->assertSame(count($data['images']), count(array_unique($data['images'])));
    }

    public function test_two_word_makes_split_correctly(): void
    {
        $parser = new PerfectMotorsParser;
        $method = new \ReflectionMethod($parser, 'splitTitle');
        $method->setAccessible(true);

        $this->assertSame([2020, 'Land Rover', 'Range Rover Sport'], $method->invoke($parser, '2020 Land Rover Range Rover Sport'));
        $this->assertSame([2019, 'Mercedes Benz', 'C200'], $method->invoke($parser, '2019 Mercedes Benz C200'));
        $this->assertSame([2015, 'Toyota', 'Land Cruiser'], $method->invoke($parser, '2015 Toyota Land Cruiser'));
    }

    public function test_parser_returns_null_for_non_car_page(): void
    {
        $this->assertNull((new PerfectMotorsParser)->parseDetailPage('<html><body>Server Error 500</body></html>', 'x'));
    }

    public function test_sweep_upserts_products_with_cdn_images_and_is_rerunnable(): void
    {
        $this->seedCategories();
        $this->fakeSite([64000, 64001, 64002]);

        $this->artisan('scrape:perfectmotors', [
            '--min-id' => 64000, '--max-id' => 64010, '--pool' => 1,
        ])->assertSuccessful();

        $this->assertSame(3, Products::where('website', 'perfectmotors')->count());

        $product = Products::where('product_link', 'https://perfect-motors.com/productDetail/64000')->first();
        $this->assertNotNull($product);
        $this->assertStringStartsWith('https://sm-perfectmotors.b-cdn.net/', $product->front_image);
        $this->assertStringStartsWith('https://perfect-motors.com/', $product->front_image_source);
        $this->assertSame('SM' . $product->id, $product->stock_code);
        $this->assertSame('United Arab Emirates', $product->country);
        $this->assertSame(5670.0, (float) $product->price); // USD verbatim
        $this->assertSame(1995, $product->year);
        $this->assertNotNull($product->make_id);
        $this->assertSame('Nissan', Categories::find($product->make_id)->cat_title);
        $this->assertNotEmpty($product->other_images);
        $this->assertStringStartsWith('https://sm-perfectmotors.b-cdn.net/', $product->other_images[0]);
        $this->assertTrue($product->show_price); // perfectmotors is a price-visible site

        // rerun the same range banks nothing new (all already exist)
        $this->artisan('scrape:perfectmotors', [
            '--min-id' => 64000, '--max-id' => 64010, '--pool' => 1, '--start-id' => 64000,
        ])->assertSuccessful();
        $this->assertSame(3, Products::where('website', 'perfectmotors')->count());
    }

    public function test_invalid_ids_are_skipped_not_fatal(): void
    {
        $this->seedCategories();
        // only 64005 is a live car; the rest of the window 500s
        $this->fakeSite([64005]);

        $this->artisan('scrape:perfectmotors', [
            '--min-id' => 64000, '--max-id' => 64010, '--pool' => 1,
        ])->assertSuccessful();

        // exactly one car banked; the 500s were skipped, not fatal
        $this->assertSame(1, Products::where('website', 'perfectmotors')->count());
        $this->assertNotNull(Products::where('product_link', 'https://perfect-motors.com/productDetail/64005')->first());
        $this->assertFileExists(config('cdn.state_dir') . '/perfectmotors-scrape.done');
    }

    public function test_dry_run_writes_report_but_no_products(): void
    {
        $this->seedCategories();
        $this->fakeSite([64000, 64001]);

        $report = config('cdn.state_dir') . '/pm-preview.html';
        $this->artisan('scrape:perfectmotors', [
            '--min-id' => 64000, '--max-id' => 64005, '--pool' => 1,
            '--dry-run' => true, '--report' => $report,
        ])->assertSuccessful();

        $this->assertSame(0, Products::where('website', 'perfectmotors')->count());
        $this->assertFileExists($report);
        $html = file_get_contents($report);
        $this->assertStringContainsString('sm-perfectmotors.b-cdn.net', $html);
        $this->assertStringContainsString('Nissan Homy', $html);
        // dry-run must not create make categories
        $this->assertSame(0, Categories::where('type', 'make')->count());
    }

    public function test_writes_progress_snapshot_and_done_marker(): void
    {
        $this->seedCategories();
        $this->fakeSite([64000, 64001]);

        $this->artisan('scrape:perfectmotors', [
            '--min-id' => 64000, '--max-id' => 64005, '--pool' => 1,
        ])->assertSuccessful();

        $progressFile = config('cdn.state_dir') . '/perfectmotors-progress.json';
        $this->assertFileExists($progressFile);
        $p = json_decode(file_get_contents($progressFile), true);
        $this->assertSame('perfectmotors', $p['source']);
        $this->assertTrue($p['done']);
        $this->assertSame(2, $p['products_scraped']);
        $this->assertGreaterThan(0, $p['images_scraped']);
        $this->assertArrayHasKey('updated_at', $p);
        $this->assertFileExists(config('cdn.state_dir') . '/perfectmotors-heartbeat.txt');
        $this->assertFileExists(config('cdn.state_dir') . '/perfectmotors.cursor');
    }

    public function test_fill_incomplete_completes_partial_products(): void
    {
        $this->seedCategories();

        // a partial product: banked earlier, specifications still NULL
        $id = Products::create([
            'title' => 'Partial car', 'website' => 'perfectmotors', 'specifications' => null,
            'product_link' => 'https://perfect-motors.com/productDetail/70795',
            'front_image' => 'https://sm-perfectmotors.b-cdn.net/1',
        ])->id;
        $this->assertNull(Products::find($id)->specifications);

        Http::fake([
            'perfect-motors.com/productDetail/*' => Http::response($this->fixture('detail-page.html'), 200),
        ]);

        $this->artisan('scrape:perfectmotors', ['--fill-incomplete' => true])->assertSuccessful();

        $p = Products::find($id);
        $this->assertIsArray($p->specifications);
        $this->assertSame('234,100 Km', $p->specifications['Milage']);
        $this->assertSame(8, $p->seats);
        $this->assertSame(2660, $p->engine_cc);
        $this->assertGreaterThan(10, count($p->other_images)); // full gallery applied
        $this->assertFileExists(config('cdn.state_dir') . '/perfectmotors-fill.done');

        $this->assertSame(0, Products::where('website', 'perfectmotors')->whereNull('specifications')->count());
    }

    public function test_limit_stops_the_sweep_early(): void
    {
        $this->seedCategories();
        $this->fakeSite([64000, 64001, 64002, 64003, 64004]);

        $this->artisan('scrape:perfectmotors', [
            '--min-id' => 64000, '--max-id' => 64010, '--pool' => 1, '--limit' => 2,
        ])->assertSuccessful();

        $this->assertSame(2, Products::where('website', 'perfectmotors')->count());
    }
}
