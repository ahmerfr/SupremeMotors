<?php

namespace Tests\Feature;

use App\Console\Commands\UploadLocalImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UploadLocalImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_swap_rewrites_local_paths_and_preserves_sources(): void
    {
        $id = DB::table('products')->insertGetId([
            'title' => 'Local image unit',
            'front_image' => 'product_images/front.jpg',
            'other_images' => json_encode(['product_images/g1.jpg', 'https://sm-tcv.b-cdn.net/img/x.jpg']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $untouchedId = DB::table('products')->insertGetId([
            'title' => 'Remote image unit',
            'front_image' => 'https://sm-linemedia.b-cdn.net/img/y.jpg',
            'other_images' => json_encode(['https://sm-linemedia.b-cdn.net/img/z.jpg']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('products:upload-local-images', ['--skip-upload' => true, '--swap' => true])
            ->assertSuccessful();

        $row = DB::table('products')->find($id);
        $this->assertSame(UploadLocalImages::CDN . '/product_images/front.jpg', $row->front_image);
        $this->assertSame('product_images/front.jpg', $row->front_image_source);
        $this->assertSame(
            [UploadLocalImages::CDN . '/product_images/g1.jpg', 'https://sm-tcv.b-cdn.net/img/x.jpg'],
            json_decode($row->other_images, true)
        );
        $this->assertSame(['product_images/g1.jpg', 'https://sm-tcv.b-cdn.net/img/x.jpg'], json_decode($row->other_images_source, true));

        $untouched = DB::table('products')->find($untouchedId);
        $this->assertSame('https://sm-linemedia.b-cdn.net/img/y.jpg', $untouched->front_image);
        $this->assertNull($untouched->front_image_source);
    }
}
