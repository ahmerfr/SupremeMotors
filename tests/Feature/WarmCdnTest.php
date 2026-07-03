<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarmCdnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cdn.state_dir' => sys_get_temp_dir() . '/warmcdn-test-' . uniqid()]);
    }

    private function makeProduct(string $front, array $gallery): int
    {
        return DB::table('products')->insertGetId([
            'title' => 'Unit',
            'front_image' => $front,
            'other_images' => json_encode($gallery),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_404_marks_dead_but_5xx_and_2xx_do_not(): void
    {
        $id = $this->makeProduct('https://sm-tcv.b-cdn.net/img/dead.jpg', [
            'https://sm-tcv.b-cdn.net/img/alive.jpg',
            'https://sm-tcv.b-cdn.net/img/gone.jpg',
            'https://sm-tcv.b-cdn.net/img/flaky.jpg',
        ]);

        Http::fake([
            'sm-tcv.b-cdn.net/img/dead.jpg' => Http::response('', 404),
            'sm-tcv.b-cdn.net/img/alive.jpg' => Http::response('', 200),
            'sm-tcv.b-cdn.net/img/gone.jpg' => Http::response('', 410),
            'sm-tcv.b-cdn.net/img/flaky.jpg' => Http::response('', 503),
        ]);

        $this->artisan('products:warm-cdn')->assertSuccessful();

        $row = DB::table('products')->find($id);
        $this->assertNotNull($row->front_image_dead_at, '404 front must be marked dead');
        $this->assertSame(
            ['https://sm-tcv.b-cdn.net/img/alive.jpg', 'https://sm-tcv.b-cdn.net/img/flaky.jpg'],
            json_decode($row->other_images, true),
            '410 dropped; 200 and 503 kept'
        );

        // checkpoint + retry log written in the test dir
        $this->assertFileExists(config('cdn.state_dir') . '/warm.cursor');
        $this->assertStringContainsString('flaky.jpg', file_get_contents(config('cdn.state_dir') . '/warm-retry.log'));
        $this->assertFileExists(config('cdn.state_dir') . '/warm.done');
    }

    public function test_outage_never_marks_dead_or_advances_checkpoint(): void
    {
        $id = $this->makeProduct('https://sm-tcv.b-cdn.net/img/a.jpg', [
            'https://sm-tcv.b-cdn.net/img/b.jpg',
        ]);

        Http::fake(fn ($request) => throw new \GuzzleHttp\Exception\ConnectException(
            'net down',
            new \GuzzleHttp\Psr7\Request('HEAD', (string) $request->url())
        ));

        $this->artisan('products:warm-cdn', ['--max-outage-retries' => 0])->assertFailed();

        $row = DB::table('products')->find($id);
        $this->assertNull($row->front_image_dead_at, 'outage must not mark anything dead');
        $this->assertSame(['https://sm-tcv.b-cdn.net/img/b.jpg'], json_decode($row->other_images, true), 'gallery untouched during outage');
        $this->assertFileDoesNotExist(config('cdn.state_dir') . '/warm.cursor');
        $this->assertFileDoesNotExist(config('cdn.state_dir') . '/warm.done');
    }
}
