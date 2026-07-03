<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Products;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CdnSwapWarmTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Products
    {
        $category = Categories::firstOrCreate(
            ['cat_title' => 'Cars', 'type' => 'category'],
            ['description' => '--'],
        );

        return Products::create(array_merge([
            'title' => 'Test Car', 'website' => 'agronetto', 'category_id' => $category->id,
            'price' => 100, 'country' => 'Europe',
            'front_image' => 'https://img.linemedia.com/img/x/front.jpg',
            'other_images' => ['https://img.linemedia.com/img/x/g1.jpg', 'https://www.tc-v.com/cdn/y/g2.jpg'],
            'product_details' => '<p>x</p>', 'stock_code' => 'SM-C1',
        ], $overrides));
    }

    public function test_swap_rewrites_urls_and_keeps_sources(): void
    {
        $product = $this->makeProduct();

        $this->artisan('products:swap-to-cdn')->assertSuccessful();

        $product->refresh();
        $this->assertSame('https://sm-linemedia.b-cdn.net/img/x/front.jpg', $product->front_image);
        $this->assertSame('https://img.linemedia.com/img/x/front.jpg', $product->front_image_source);
        $this->assertSame(
            ['https://sm-linemedia.b-cdn.net/img/x/g1.jpg', 'https://sm-tcv.b-cdn.net/cdn/y/g2.jpg'],
            $product->other_images,
        );
        $this->assertStringContainsString('img.linemedia.com', $product->other_images_source);
    }

    public function test_swap_is_idempotent(): void
    {
        $product = $this->makeProduct();
        $this->artisan('products:swap-to-cdn')->assertSuccessful();
        $first = $product->fresh()->front_image;

        $this->artisan('products:swap-to-cdn')->assertSuccessful();

        $this->assertSame($first, $product->fresh()->front_image);
        $this->assertSame('https://img.linemedia.com/img/x/front.jpg', $product->fresh()->front_image_source);
    }

    public function test_warm_marks_dead_front_and_drops_dead_gallery(): void
    {
        $product = $this->makeProduct([
            'front_image' => 'https://sm-linemedia.b-cdn.net/img/x/front.jpg',
            'other_images' => ['https://sm-linemedia.b-cdn.net/img/x/alive.jpg', 'https://sm-linemedia.b-cdn.net/img/x/gone.jpg'],
        ]);

        Http::fake([
            'sm-linemedia.b-cdn.net/img/x/gone.jpg' => Http::response('', 404),
            'sm-linemedia.b-cdn.net/*' => Http::response('', 200),
        ]);

        $this->artisan('products:warm-cdn')->assertSuccessful();

        $product->refresh();
        $this->assertNull($product->front_image_dead_at);
        $this->assertSame(['https://sm-linemedia.b-cdn.net/img/x/alive.jpg'], $product->other_images);

        // and a product whose CDN front 404s gets marked dead
        $deadFront = $this->makeProduct([
            'front_image' => 'https://sm-linemedia.b-cdn.net/img/x/gone.jpg',
            'other_images' => [], 'stock_code' => 'SM-C2',
        ]);
        $this->artisan('products:warm-cdn')->assertSuccessful();
        $this->assertNotNull($deadFront->fresh()->front_image_dead_at);
    }
}
