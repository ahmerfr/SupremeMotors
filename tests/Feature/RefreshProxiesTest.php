<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshProxiesTest extends TestCase
{
    private function outFile(): string
    {
        @mkdir(config('cdn.state_dir'), 0777, true);

        return config('cdn.state_dir') . '/proxies.txt';
    }

    public function test_harvests_and_writes_proxies(): void
    {
        Http::fake([
            '*proxyscrape*' => Http::response("1.2.3.4:8080\n5.6.7.8:3128\n"),
            '*githubusercontent*' => Http::response("5.6.7.8:3128\n9.9.9.9:80\ngarbage-line\n"),
            '*' => Http::response(''),
        ]);

        $out = $this->outFile();
        $this->artisan('scrape:refresh-proxies', ['--out' => $out])->assertSuccessful();

        $lines = array_values(array_filter(array_map('trim', file($out)), fn ($l) => $l !== '' && !str_starts_with($l, '#')));
        sort($lines);
        $this->assertSame(['1.2.3.4:8080', '5.6.7.8:3128', '9.9.9.9:80'], $lines); // deduped, garbage dropped
    }

    public function test_validate_keeps_only_reachable_proxies(): void
    {
        Http::fake([
            '*proxyscrape*' => Http::response("1.1.1.1:80\n2.2.2.2:80\n"),
            '*githubusercontent*' => Http::response(''),
            'www.autotrader.co.za/*' => function ($request) {
                // pretend only the first proxy reaches the target
                return Http::response('ok', 200);
            },
        ]);

        $out = $this->outFile();
        $this->artisan('scrape:refresh-proxies', [
            '--validate' => true, '--out' => $out, '--timeout' => 3,
        ])->assertSuccessful();

        $lines = array_values(array_filter(array_map('trim', file($out)), fn ($l) => $l !== '' && !str_starts_with($l, '#')));
        $this->assertContains('1.1.1.1:80', $lines);
    }

    public function test_append_merges_with_existing(): void
    {
        Http::fake([
            '*proxyscrape*' => Http::response("3.3.3.3:80\n"),
            '*' => Http::response(''),
        ]);

        $out = $this->outFile();
        file_put_contents($out, "# header\n1.1.1.1:80\n");

        $this->artisan('scrape:refresh-proxies', ['--out' => $out, '--append' => true])->assertSuccessful();

        $lines = array_values(array_filter(array_map('trim', file($out)), fn ($l) => $l !== '' && !str_starts_with($l, '#')));
        sort($lines);
        $this->assertSame(['1.1.1.1:80', '3.3.3.3:80'], $lines);
    }
}
