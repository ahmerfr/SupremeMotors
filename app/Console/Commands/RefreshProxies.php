<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Harvest free HTTP proxies from public lists and (optionally) validate each
 * against a live target, writing the survivors to the proxy file the scraper
 * reads. Free proxies churn fast, so this is meant to run on a schedule while
 * a scrape is in flight — the scraper hot-reloads the file between pages, so a
 * fresh top-up keeps the working pool alive without a restart.
 *
 * Reality check: free/datacenter proxies are frequently blocked outright by
 * WAFs. --validate against the actual target is the only honest filter — a
 * proxy that returns 200 from autotrader.co.za is worth keeping; one that
 * 403/503s is not, no matter how "alive" it looks against a neutral URL.
 */
class RefreshProxies extends Command
{
    protected $signature = 'scrape:refresh-proxies
        {--validate : Test each proxy against --validate-url and keep only 200s}
        {--validate-url=https://www.autotrader.co.za/cars-for-sale : Target to validate against}
        {--limit=400 : Cap how many raw proxies to test (validation is the slow part)}
        {--pool=50 : Concurrent validation requests}
        {--timeout=8 : Per-proxy validation timeout (seconds)}
        {--out= : Output file (default: <cdn.state_dir>/proxies.txt)}
        {--append : Merge with the existing file instead of replacing it}';

    protected $description = 'Harvest + validate free HTTP proxies into the scraper proxy pool file';

    /** public raw proxy lists (host:port per line) */
    private const SOURCES = [
        'https://api.proxyscrape.com/v2/?request=displayproxies&protocol=http&timeout=10000&country=all&ssl=all&anonymity=all',
        'https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt',
        'https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt',
        'https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/http.txt',
        'https://raw.githubusercontent.com/proxifly/free-proxy-list/main/proxies/protocols/http/data.txt',
        'https://raw.githubusercontent.com/jetkai/proxy-list/main/online-proxies/txt/proxies-http.txt',
        'https://raw.githubusercontent.com/sunny9577/proxy-scraper/master/generated/http_proxies.txt',
        'https://raw.githubusercontent.com/ShiftyTR/Proxy-List/master/http.txt',
        'https://raw.githubusercontent.com/mmpx12/proxy-list/master/http.txt',
        'https://raw.githubusercontent.com/roosterkid/openproxylist/main/HTTPS_RAW.txt',
    ];

    public function handle(): int
    {
        $out = $this->option('out') ?: config('cdn.state_dir', storage_path('app/cdn')) . '/proxies.txt';
        @mkdir(dirname($out), 0777, true);

        $raw = [];
        foreach (self::SOURCES as $url) {
            try {
                $resp = Http::timeout(20)->get($url);
                if ($resp->successful()) {
                    foreach (preg_split('/\s+/', trim($resp->body())) as $line) {
                        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}:\d{2,5}$/', trim($line))) {
                            $raw[trim($line)] = true;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('source failed: ' . parse_url($url, PHP_URL_HOST) . ' — ' . $e->getMessage());
            }
        }

        $raw = array_keys($raw);
        $this->info('harvested ' . count($raw) . ' unique proxies from ' . count(self::SOURCES) . ' sources');
        if (!$raw) {
            $this->error('no proxies harvested — sources unreachable?');

            return self::FAILURE;
        }

        $keep = $raw;
        if ($this->option('validate')) {
            $keep = $this->validate(array_slice($raw, 0, (int) $this->option('limit')));
            $this->info('validated: ' . count($keep) . ' live against ' . $this->option('validate-url'));
        }

        if ($this->option('append') && is_file($out)) {
            $existing = array_filter(array_map('trim', file($out)), fn ($l) => $l !== '' && !str_starts_with($l, '#'));
            $keep = array_values(array_unique([...$existing, ...$keep]));
        }

        $header = '# free proxy pool — refreshed ' . now()->toDateTimeString() . " (" . count($keep) . " entries)\n";
        file_put_contents($out, $header . implode("\n", $keep) . "\n");
        $this->info('wrote ' . count($keep) . ' proxies to ' . $out);

        if ($this->option('validate') && count($keep) < 5) {
            $this->warn('very few live proxies — free pool may be WAF-blocked by the target; residential proxies would be more reliable.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  string[]  $proxies
     * @return string[]  the ones that returned a 2xx from the target
     */
    private function validate(array $proxies): array
    {
        $url = $this->option('validate-url');
        $timeout = (int) $this->option('timeout');
        $poolSize = max(1, (int) $this->option('pool'));

        if (app()->runningUnitTests()) {
            // deterministic, fakeable path
            $live = [];
            foreach ($proxies as $p) {
                try {
                    if (Http::timeout($timeout)->withOptions(['proxy' => 'http://' . $p])->get($url)->successful()) {
                        $live[] = $p;
                    }
                } catch (\Throwable) {
                }
            }

            return $live;
        }

        $client = new \GuzzleHttp\Client(['timeout' => $timeout, 'connect_timeout' => $timeout, 'http_errors' => false]);
        $live = [];
        $bar = $this->output->createProgressBar(count($proxies));

        $requests = function () use ($proxies, $client, $url) {
            foreach ($proxies as $p) {
                yield $p => $client->getAsync($url, ['proxy' => 'http://' . $p]);
            }
        };

        \GuzzleHttp\Promise\Each::ofLimit(
            $requests(),
            $poolSize,
            function ($resp, $p) use (&$live, $bar) {
                if ($resp->getStatusCode() >= 200 && $resp->getStatusCode() < 300) {
                    $live[] = $p;
                }
                $bar->advance();
            },
            function ($_e, $_p) use ($bar) {
                $bar->advance();
            }
        )->wait();

        $bar->finish();
        $this->newLine();

        return $live;
    }
}
