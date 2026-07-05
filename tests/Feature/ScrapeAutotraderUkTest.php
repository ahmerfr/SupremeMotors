<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Products;
use App\Services\AutotraderUkParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapeAutotraderUkTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/autotraderuk/{$name}"));
    }

    private function payload(): array
    {
        return json_decode($this->fixture('search-response.json'), true);
    }

    private function seedCategories(): void
    {
        Categories::create(['cat_title' => 'Cars', 'type' => 'category']);
    }

    /**
     * Fake the Cloudflare handshake (homepage, sets __cf_bm) + the gateway POST.
     * The gateway always answers with the saved search-response fixture so the
     * command's mapping is exercised end to end without a live call.
     */
    private function fakeGateway(): void
    {
        Http::fake([
            'www.autotrader.co.uk/at-gateway*' => Http::response($this->payload(), 200),
            'www.autotrader.co.uk/' => Http::response('<html>ok</html>', 200, [
                'Set-Cookie' => '__cf_bm=abc123; path=/; HttpOnly',
            ]),
        ]);
    }

    public function test_parser_maps_all_fields_from_the_fixture(): void
    {
        $result = (new AutotraderUkParser)->parseSearchResponse($this->payload());

        // 27 raw rows -> 24 real cars (GPT ad + 2 cross-sell repeats dropped)
        $this->assertGreaterThanOrEqual(20, count($result['listings']));
        $this->assertSame(22806, $result['last_page']); // page.count = total pages
        $this->assertSame(456102, $result['total']);     // results.count = total cars

        $jag = collect($result['listings'])->firstWhere('advert_id', '202606183396342');
        $this->assertNotNull($jag);
        $this->assertSame('Jaguar X-Type', $jag['title']);
        $this->assertSame('Jaguar', $jag['make']);
        $this->assertSame('X-Type', $jag['model']);
        $this->assertSame(2004, $jag['year']);
        $this->assertSame(500, $jag['price']); // numeric GBP from advertContext
        $this->assertSame(118000, $jag['mileage_km']); // "118,000 miles" badge
        $this->assertSame(2500, $jag['engine_cc']); // "2.5 V6 ..." subTitle
        $this->assertSame('Used', $jag['condition']);
        $this->assertSame('Right', $jag['steering']);
        $this->assertSame('United Kingdom', $jag['country']);
        $this->assertSame('https://www.autotrader.co.uk/car-details/202606183396342', $jag['product_link']);

        // images: {resize} placeholder expanded, host preserved
        $this->assertNotEmpty($jag['images']);
        $this->assertStringContainsString('m.atcdn.co.uk', $jag['images'][0]);
        $this->assertStringNotContainsString('{resize}', $jag['images'][0]);

        // specifications carries the raw subTitle + location + price display etc.
        $this->assertIsArray($jag['specifications']);
        $this->assertSame('2.5 V6 Classic (AWD) 5dr', $jag['specifications']['subTitle']);
        $this->assertSame('miles', $jag['specifications']['mileageUnit']);
        $this->assertSame('GBP', $jag['specifications']['priceCurrency']);
        $this->assertSame('Reading (39 miles)', $jag['specifications']['vehicleLocation']);
    }

    public function test_subtitle_parser_infers_engine_fuel_transmission(): void
    {
        $parser = new AutotraderUkParser;

        $diesel = $parser->parseSubTitle('2.0 dCi Dynamique S Nav 4WD Euro 6 (s/s) 5dr');
        $this->assertSame(2000, $diesel['engine_cc']);
        $this->assertSame('Diesel', $diesel['fuel']);
        $this->assertNull($diesel['transmission']);

        $petrolAuto = $parser->parseSubTitle('1.6 THP Auto');
        $this->assertSame(1600, $petrolAuto['engine_cc']);
        $this->assertSame('Petrol', $petrolAuto['fuel']);
        $this->assertSame('Automatic', $petrolAuto['transmission']);

        $dsg = $parser->parseSubTitle('2.0 TDI 150 SE Technology DSG 5dr');
        $this->assertSame('Automatic', $dsg['transmission']);

        $manual = $parser->parseSubTitle('1.25 Zetec Manual 5dr');
        $this->assertSame(1250, $manual['engine_cc']);
        $this->assertSame('Manual', $manual['transmission']);
        $this->assertNull($manual['fuel']); // no fuel code -> best-effort null

        // empty subTitle degrades to all-null, never throws
        $empty = $parser->parseSubTitle(null);
        $this->assertNull($empty['engine_cc']);
    }

    public function test_ad_rows_and_cross_sell_repeats_are_skipped(): void
    {
        $parser = new AutotraderUkParser;

        // a GPT ad slot has no advertId -> null
        $this->assertNull($parser->parseListing(['type' => 'GPT_LISTING', 'posId' => 'x']));

        // a YOU_MAY_ALSO_LIKE cross-sell row is dropped even with a real advertId
        $this->assertNull($parser->parseListing([
            'type' => 'YOU_MAY_ALSO_LIKE',
            'advertId' => '999',
            'title' => 'Ford Focus',
            'trackingContext' => ['advertContext' => ['price' => 5000, 'make' => 'Ford', 'model' => 'Focus']],
        ]));

        // a real natural listing with no price (sold / POA) is skipped
        $this->assertNull($parser->parseListing([
            'type' => 'NATURAL_LISTING',
            'advertId' => '1000',
            'title' => 'Ford Focus',
            'trackingContext' => ['advertContext' => ['make' => 'Ford', 'model' => 'Focus']],
        ]));
    }

    public function test_command_upserts_products_with_cdn_images(): void
    {
        $this->seedCategories();
        $this->fakeGateway();

        $this->artisan('scrape:autotraderuk', ['--max-pages' => 1, '--delay-ms' => 0])
            ->assertSuccessful();

        $this->assertGreaterThanOrEqual(20, Products::where('website', 'autotraderuk')->count());

        $product = Products::where('product_link', 'like', '%202606183396342')->first();
        $this->assertNotNull($product);
        $this->assertSame('Jaguar X-Type', $product->title);
        $this->assertSame(2004, $product->year);
        $this->assertSame(500.0, (float) $product->price); // GBP verbatim
        $this->assertSame(118000, $product->mileage_km);
        $this->assertSame('United Kingdom', $product->country);
        $this->assertSame('Right', $product->steering);
        $this->assertSame('SM' . $product->id, $product->stock_code);

        // images rewritten onto the Bunny pull zone, originals kept in *_source
        $this->assertStringStartsWith('https://sm-autotraderuk.b-cdn.net/', $product->front_image);
        $this->assertStringStartsWith('https://m.atcdn.co.uk/', $product->front_image_source);
        $this->assertStringNotContainsString('{resize}', $product->front_image);
        $this->assertNotEmpty($product->other_images);
        $this->assertStringStartsWith('https://sm-autotraderuk.b-cdn.net/', $product->other_images[0]);

        // make normalised + linked
        $this->assertNotNull($product->make_id);
        $this->assertSame('Jaguar', Categories::find($product->make_id)->cat_title);

        // specifications JSON round-trips
        $this->assertIsArray($product->specifications);
        $this->assertSame('2.5 V6 Classic (AWD) 5dr', $product->specifications['subTitle']);
    }

    public function test_price_is_visible_for_autotraderuk(): void
    {
        $this->seedCategories();
        $this->fakeGateway();

        $this->artisan('scrape:autotraderuk', ['--max-pages' => 1, '--delay-ms' => 0])->assertSuccessful();

        $product = Products::where('product_link', 'like', '%202606183396342')->first();
        // GBP prices are real -> the card shows the price (not "Enquire")
        $this->assertTrue($product->show_price);
    }

    public function test_rerun_upserts_in_place_not_duplicated(): void
    {
        $this->seedCategories();
        $this->fakeGateway();

        $this->artisan('scrape:autotraderuk', ['--max-pages' => 1, '--delay-ms' => 0, '--start-page' => 1])->assertSuccessful();
        $count = Products::where('website', 'autotraderuk')->count();

        $this->artisan('scrape:autotraderuk', ['--max-pages' => 1, '--delay-ms' => 0, '--start-page' => 1])->assertSuccessful();
        $this->assertSame($count, Products::where('website', 'autotraderuk')->count());
    }

    public function test_dry_run_writes_report_but_no_products(): void
    {
        $this->seedCategories();
        $this->fakeGateway();

        $report = config('cdn.state_dir') . '/uk-preview.html';
        $this->artisan('scrape:autotraderuk', [
            '--max-pages' => 1, '--delay-ms' => 0, '--dry-run' => true, '--report' => $report,
        ])->assertSuccessful();

        $this->assertSame(0, Products::where('website', 'autotraderuk')->count());
        $this->assertFileExists($report);
        $html = file_get_contents($report);
        $this->assertStringContainsString('sm-autotraderuk.b-cdn.net', $html);
        $this->assertStringContainsString('Jaguar X-Type', $html);
    }

    public function test_limit_stops_after_n_products(): void
    {
        $this->seedCategories();
        $this->fakeGateway();

        $this->artisan('scrape:autotraderuk', ['--limit' => 5, '--delay-ms' => 0])->assertSuccessful();
        $this->assertSame(5, Products::where('website', 'autotraderuk')->count());
    }

    public function test_make_shard_writes_scoped_state_files(): void
    {
        $this->seedCategories();
        $this->fakeGateway();

        $this->artisan('scrape:autotraderuk', ['--make' => 'Ford', '--max-pages' => 1, '--delay-ms' => 0])
            ->assertSuccessful();

        $dir = config('cdn.state_dir');
        $this->assertFileExists($dir . '/autotraderuk-ford.cursor');
        $this->assertFileExists($dir . '/autotraderuk-progress-ford.json');

        $p = json_decode(file_get_contents($dir . '/autotraderuk-progress-ford.json'), true);
        $this->assertSame('ford', $p['shard']);
        $this->assertSame('Ford', $p['make']);
        // it must NOT touch the un-sharded default files
        $this->assertFileDoesNotExist($dir . '/autotraderuk.cursor');
    }

    public function test_gateway_body_carries_required_filters_and_channel(): void
    {
        $this->seedCategories();
        $this->fakeGateway();

        $this->artisan('scrape:autotraderuk', [
            '--make' => 'Ford', '--min-price' => 5000, '--max-price' => 15000,
            '--max-pages' => 1, '--delay-ms' => 0,
        ])->assertSuccessful();

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'at-gateway')) {
                return false;
            }
            $body = $request->data()[0] ?? [];
            $vars = $body['variables'] ?? [];
            $filters = collect($vars['filters'] ?? [])->keyBy('filter');

            return ($vars['channel'] ?? null) === 'cars'                       // channel is a top-level var
                && $filters->has('postcode')
                && ($filters['price_search_type']['selected'][0] ?? null) === 'total' // required or 200-with-errors
                && ($filters['make']['selected'][0] ?? null) === 'Ford'
                && ($filters['min_price']['selected'][0] ?? null) === '5000'
                && ($filters['max_price']['selected'][0] ?? null) === '15000'
                && str_contains($body['query'] ?? '', 'SearchResultsListingsGridQuery');
        });
    }
}
