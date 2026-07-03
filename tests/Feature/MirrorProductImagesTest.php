<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Products;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MirrorProductImagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Products
    {
        $category = Categories::create(['cat_title' => 'Cars', 'type' => 'category', 'description' => '--']);

        return Products::create(array_merge([
            'title' => 'Test Car', 'website' => 'tcv', 'category_id' => $category->id,
            'price' => 100, 'country' => 'Japan', 'front_image' => 'https://img.example.com/car.jpg',
            'other_images' => [], 'product_details' => '<p>x</p>', 'stock_code' => 'SM-T1',
        ], $overrides));
    }

    private function fakeBunnyConfig(): void
    {
        config([
            'services.bunny.storage_zone' => 'testzone',
            'services.bunny.storage_key' => 'secret',
            'services.bunny.storage_host' => 'storage.bunnycdn.com',
            'services.bunny.cdn_host' => 'test.b-cdn.net',
        ]);
    }

    public function test_mirrors_front_and_gallery_dropping_dead_images(): void
    {
        $this->fakeBunnyConfig();
        $product = $this->makeProduct([
            'other_images' => ['https://img.example.com/g-alive.jpg', 'https://img.example.com/gone.jpg'],
        ]);

        Http::fake([
            'storage.bunnycdn.com/testzone/__connectivity_check.txt' => Http::response('', 201),
            'img.example.com/gone.jpg' => Http::response('gone', 404),
            'img.example.com/*' => Http::response('JPEGBYTES', 200, ['Content-Type' => 'image/jpeg']),
            'storage.bunnycdn.com/testzone/products/*' => Http::response('', 201),
        ]);

        $this->artisan('products:mirror-images')->assertSuccessful();

        $product->refresh();
        $this->assertSame("https://test.b-cdn.net/products/{$product->id}/front.jpg", $product->front_image);
        $this->assertSame('https://img.example.com/car.jpg', $product->front_image_source);
        // alive gallery image mirrored, dead one dropped
        $this->assertSame(["https://test.b-cdn.net/products/{$product->id}/g0.jpg"], $product->other_images);
        $this->assertSame(
            ['https://img.example.com/g-alive.jpg', 'https://img.example.com/gone.jpg'],
            json_decode($product->other_images_source, true),
        );
    }

    public function test_marks_dead_when_download_404s(): void
    {
        $this->fakeBunnyConfig();
        $product = $this->makeProduct();

        Http::fake([
            'storage.bunnycdn.com/testzone/__connectivity_check.txt' => Http::response('', 201),
            'img.example.com/*' => Http::response('gone', 404),
        ]);

        $this->artisan('products:mirror-images')->assertSuccessful();

        $product->refresh();
        $this->assertNotNull($product->front_image_dead_at);
        $this->assertSame('https://img.example.com/car.jpg', $product->front_image);
    }

    public function test_skips_already_mirrored_and_local_images(): void
    {
        $this->fakeBunnyConfig();
        $mirrored = $this->makeProduct(['front_image' => 'https://test.b-cdn.net/products/1/front.jpg', 'stock_code' => 'SM-T2']);
        $local = $this->makeProduct(['front_image' => 'product_images/abc.jpg', 'stock_code' => 'SM-T3']);

        Http::fake([
            'storage.bunnycdn.com/testzone/__connectivity_check.txt' => Http::response('', 201),
        ]);

        $this->artisan('products:mirror-images')->assertSuccessful();

        Http::assertSentCount(1); // only the connectivity probe
        $this->assertSame('https://test.b-cdn.net/products/1/front.jpg', $mirrored->fresh()->front_image);
        $this->assertSame('product_images/abc.jpg', $local->fresh()->front_image);
    }

    public function test_fails_fast_without_credentials(): void
    {
        config(['services.bunny.storage_zone' => null, 'services.bunny.storage_key' => null, 'services.bunny.cdn_host' => null]);

        $this->artisan('products:mirror-images')->assertFailed();
    }
}
