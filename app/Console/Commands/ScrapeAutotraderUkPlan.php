<?php

namespace App\Console\Commands;

use App\Services\AutotraderUkParser;
use App\Services\SchannelCurl;
use Illuminate\Console\Command;

/**
 * Build the Phase-1 shard plan for a full autotrader.co.uk crawl.
 *
 * WHY: a single search filter set only exposes 100 pages (~2,000 cars), but the
 * catalogue is ~456k. To reach every car we partition the whole thing into
 * PRICE BANDS, each small enough (<= --threshold) to fit under the 100-page cap.
 * The gateway hands back an exact `results.count` for any price window, so we
 * recursively split [min,max] until every leaf band is under the threshold, then
 * write the bands to a JSON file the keepalive runs one shard per band.
 *
 * Empty windows cost one count call then prune (count 0 -> no split), so the
 * skew of real prices (most cars in a narrow band) is handled automatically.
 */
class ScrapeAutotraderUkPlan extends Command
{
    protected $signature = 'scrape:autotraderuk-plan
        {--threshold=1800 : Max cars per band (must stay under the ~2,000 the 100-page cap exposes)}
        {--postcode=SW1A 1AA : Search-centre postcode (UK-wide results either way)}
        {--max-price=10000000 : Upper price bound to partition under (GBP)}
        {--out= : Where to write the shard plan JSON (default storage/app/cdn/autotraderuk-shards.json)}
        {--curl-bin= : Override the Schannel curl.exe path}';

    protected $description = 'Plan the full-catalogue Phase-1 shards as price bands under the 100-page cap';

    private const GATEWAY_URL = AutotraderUkParser::BASE . '/at-gateway?opname=SearchResultsListingsGridQuery';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private SchannelCurl $curl;

    private string $jar = '';

    private string $query = '';

    private int $countCalls = 0;

    public function handle(): int
    {
        $stateDir = config('cdn.state_dir', storage_path('app/cdn'));
        @mkdir($stateDir, 0777, true);
        $out = $this->option('out') ?: $stateDir . '/autotraderuk-shards.json';

        $this->query = (string) file_get_contents(base_path('resources/graphql/autotraderuk-search.graphql'));
        $this->curl = new SchannelCurl($this->option('curl-bin') ?: null, $stateDir);
        $this->jar = $stateDir . '/autotraderuk-plan.jar';
        @unlink($this->jar);

        // mint __cf_bm
        $this->curl->request('GET', AutotraderUkParser::BASE . '/', [
            'User-Agent' => self::USER_AGENT,
            'Accept-Language' => 'en-GB,en;q=0.9',
        ], null, $this->jar, 30);

        $threshold = max(200, (int) $this->option('threshold'));
        $maxPrice = (int) $this->option('max-price');

        $total = $this->count(null, null);
        $this->info("catalogue total: {$total} cars — partitioning into bands of <= {$threshold}");
        if ($total <= 0) {
            $this->error('count query returned 0 — session/cookie problem, aborting');

            return self::FAILURE;
        }

        $bands = [];
        $this->split(0, $maxPrice, $threshold, $bands);

        // a price filter excludes POA/price-less listings; a final unbounded shard
        // (no price filter) sweeps whatever the bands can't reach (its first 100
        // pages), so POA stock still lands
        $covered = array_sum(array_column($bands, 'count'));
        $bands[] = ['min' => null, 'max' => null, 'count' => $total - $covered, 'shard' => 'all'];

        $plan = [
            'built_at' => now()->toIso8601String(),
            'total' => $total,
            'threshold' => $threshold,
            'covered_by_bands' => $covered,
            'count_calls' => $this->countCalls,
            'bands' => array_values($bands),
        ];
        file_put_contents($out, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @unlink($this->jar);

        $this->info("wrote {$out}: " . count($bands) . " shards, {$covered}/{$total} covered by price bands, {$this->countCalls} count calls");

        return self::SUCCESS;
    }

    /**
     * Recursively split [lo,hi] until each band is <= threshold. Emits leaf bands
     * into $bands. Empty windows (count 0) prune. A window that can't split
     * further (lo==hi) is emitted as-is even if over threshold (a single-price
     * pile-up; rare, and its first 100 pages still land).
     *
     * @param  array<int,array<string,mixed>>  $bands
     */
    private function split(int $lo, int $hi, int $threshold, array &$bands): void
    {
        $c = $this->count($lo, $hi);
        if ($c <= 0) {
            return;
        }
        if ($c <= $threshold || $lo >= $hi) {
            $bands[] = ['min' => $lo, 'max' => $hi, 'count' => $c, 'shard' => "p{$lo}_{$hi}"];
            $this->line("  band £{$lo}–£{$hi}: {$c} cars");

            return;
        }
        $mid = intdiv($lo + $hi, 2);
        $this->split($lo, $mid, $threshold, $bands);
        $this->split($mid + 1, $hi, $threshold, $bands);
    }

    /** exact result count for a price window (null bound = open), via the gateway */
    private function count(?int $min, ?int $max): int
    {
        $filters = [
            ['filter' => 'postcode', 'selected' => [(string) $this->option('postcode')]],
            ['filter' => 'price_search_type', 'selected' => ['total']],
        ];
        if ($min !== null) {
            $filters[] = ['filter' => 'min_price', 'selected' => [(string) $min]];
        }
        if ($max !== null) {
            $filters[] = ['filter' => 'max_price', 'selected' => [(string) $max]];
        }
        $body = json_encode([[
            'operationName' => 'SearchResultsListingsGridQuery',
            'variables' => [
                'filters' => $filters,
                'channel' => 'cars',
                'page' => 1,
                'sortBy' => 'relevance',
                'listingType' => null,
                'searchId' => '00000000-0000-0000-0000-000000000000',
                'featureFlags' => [],
            ],
            'query' => $this->query,
        ]]);

        // a full threshold=1800 plan fires ~600-1000 count calls; a light jitter
        // between them keeps the cadence off Cloudflare's rate radar
        usleep(random_int(120, 320) * 1000);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->countCalls++;
            [$status, $payload] = $this->curl->request('POST', self::GATEWAY_URL, [
                'User-Agent' => self::USER_AGENT,
                'Content-Type' => 'application/json',
                'Origin' => AutotraderUkParser::BASE,
                'Referer' => AutotraderUkParser::BASE . '/car-search',
                'x-sauron-app-name' => 'sauron-search-results-app',
            ], $body, $this->jar, 40);

            if ($status === 200) {
                $j = json_decode($payload, true);
                $count = $j[0]['data']['searchResults']['page']['results']['count'] ?? null;
                if (is_int($count)) {
                    return $count;
                }
            }
            // challenged/blip — re-mint cookie and back off
            $this->curl->request('GET', AutotraderUkParser::BASE . '/', ['User-Agent' => self::USER_AGENT], null, $this->jar, 30);
            sleep($attempt);
        }

        // treat as "assume over threshold so it gets split" would loop forever on
        // a dead session; instead return 0 to prune and log — keepalive reruns plan
        $this->warn("count(£{$min}–£{$max}) failed after retries — pruned");

        return 0;
    }
}
