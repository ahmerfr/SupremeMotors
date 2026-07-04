<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WarmCdn extends Command
{
    protected $signature = 'products:warm-cdn
        {--start-id= : Force a starting product id (default: resume from checkpoint)}
        {--scope=all : all | fronts (fronts warms only front images, own checkpoint)}
        {--pool= : concurrent HEADs (default 100)}
        {--max-outage-retries=-1 : Give up after this many consecutive same-batch retries (-1 = never)}';

    protected $description = 'Request every CDN image once so Perma-Cache stores a permanent copy; resumable, outage-tolerant';

    // HEADs mostly wait on Bunny's origin fetch (2-5s on a cache miss), so the
    // pool sets throughput — but past the origin's tolerance connections start
    // dying in 45s timeouts and goodput collapses. 100 is the balance point.
    private const POOL = 100;

    /** Only these statuses prove the origin lost the file. Everything else is retryable. */
    private const DEAD_STATUSES = [404, 410];

    private const OUTAGE_SLEEP_SECONDS = 45;

    private int $pool = self::POOL;

    public function handle(): int
    {
        $frontsOnly = $this->option('scope') === 'fronts';
        $this->pool = (int) ($this->option('pool') ?: self::POOL);

        @mkdir(config('cdn.state_dir', storage_path('app/cdn')), 0777, true);
        $suffix = $frontsOnly ? '-fronts' : '';
        $checkpointFile = config('cdn.state_dir', storage_path('app/cdn')) . "/warm{$suffix}.cursor";
        $retryLog = config('cdn.state_dir', storage_path('app/cdn')) . '/warm-retry.log';

        $cursor = $this->option('start-id') !== null
            ? (int) $this->option('start-id')
            : (int) @file_get_contents($checkpointFile);

        $maxOutageRetries = (int) $this->option('max-outage-retries');
        $stats = ['products' => 0, 'warmed' => 0, 'deadFront' => 0, 'deadGallery' => 0, 'retryable' => 0];
        $this->info('warming ' . ($frontsOnly ? 'FRONTS ONLY ' : '') . "from cursor {$cursor} (pool {$this->pool})");

        while (true) {
            $rows = DB::table('products')
                ->where('id', '>', $cursor)
                ->whereNull('front_image_dead_at')
                ->where(fn ($q) => $q
                    ->where('front_image', 'like', '%.b-cdn.net%')
                    ->when(! $frontsOnly, fn ($qq) => $qq->orWhere('other_images', 'like', '%.b-cdn.net%')))
                ->orderBy('id')
                ->limit($frontsOnly ? 500 : 200)
                ->get(['id', 'front_image', 'other_images']);
            if ($rows->isEmpty()) {
                break;
            }

            $jobs = [];
            foreach ($rows as $r) {
                if (str_contains((string) $r->front_image, '.b-cdn.net')) {
                    $jobs["{$r->id}|front"] = $r->front_image;
                }
                if (! $frontsOnly) {
                    foreach (json_decode($r->other_images ?? '[]', true) ?: [] as $i => $url) {
                        if (is_string($url) && str_contains($url, '.b-cdn.net')) {
                            $jobs["{$r->id}|g{$i}"] = $url;
                        }
                    }
                }
            }

            // probe the batch; classify each URL. On outage rounds, only the
            // failing subset is retried — successes are banked across rounds.
            $outageRetries = 0;
            $attempt = $jobs;
            $dead = [];
            $retryable = [];
            $warmed = 0;
            while (true) {
                [$deadRound, $retryRound, $warmedRound, $connFailures] = $this->probe($attempt);
                $dead += $deadRound;
                $warmed += $warmedRound;

                // Circuit breaker: if most of the attempt failed at the
                // connection level the network is down, not the images. Never
                // mark anything dead or advance the cursor on outage signal.
                $isOutage = count($attempt) > 0 && $connFailures / count($attempt) > 0.5;
                if (! $isOutage) {
                    $retryable += $retryRound;
                    break;
                }
                $outageRetries++;
                if ($maxOutageRetries >= 0 && $outageRetries > $maxOutageRetries) {
                    $this->warn("network outage persisted; stopping at cursor {$cursor} (resume will pick up here)");

                    return self::FAILURE;
                }
                // Partial-failure escape: rounds that keep half-failing while
                // the network is demonstrably alive (>=10% succeeding) mean a
                // struggling origin, not an outage — log the stragglers for the
                // retry pass and move on rather than stalling the whole crawl.
                // A true outage (>=90% failing) keeps retrying forever.
                $partiallyAlive = $connFailures / count($attempt) < 0.9;
                if ($outageRetries >= 6 && $partiallyAlive) {
                    $retryable += $retryRound;
                    $this->warn('persistent partial failures after ' . $outageRetries . ' rounds — logging ' . count($retryRound) . ' stragglers and moving on');
                    break;
                }
                $this->warn("network outage detected ({$connFailures}/" . count($attempt) . ' failed) — retrying failed subset in ' . self::OUTAGE_SLEEP_SECONDS . 's');
                $attempt = $retryRound;
                sleep(self::OUTAGE_SLEEP_SECONDS);
            }

            $stats['warmed'] += $warmed;
            $stats['retryable'] += count($retryable);
            if ($retryable !== []) {
                $lines = '';
                foreach ($retryable as $k => $url) {
                    $lines .= "{$k}\t{$url}\n";
                }
                @file_put_contents($retryLog, $lines, FILE_APPEND);
            }

            // apply confirmed-dead marks / gallery drops
            foreach ($rows as $r) {
                $update = [];
                if (isset($dead["{$r->id}|front"])) {
                    $update['front_image_dead_at'] = now();
                    $stats['deadFront']++;
                }
                $gallery = json_decode($r->other_images ?? '[]', true) ?: [];
                $newGallery = [];
                $changed = false;
                foreach ($gallery as $i => $url) {
                    if (isset($dead["{$r->id}|g{$i}"])) {
                        $changed = true;
                        $stats['deadGallery']++;
                    } else {
                        $newGallery[] = $url;
                    }
                }
                if ($changed) {
                    $update['other_images'] = json_encode(array_values($newGallery));
                }
                if ($update !== []) {
                    DB::table('products')->where('id', $r->id)->update($update);
                }
            }

            // advance + persist the checkpoint only after the batch fully landed
            $cursor = $rows->last()->id;
            file_put_contents($checkpointFile, (string) $cursor);

            $stats['products'] += $rows->count();
            if ($stats['products'] % 5000 < 200) {
                $this->info("products {$stats['products']} | warmed {$stats['warmed']} | dead front {$stats['deadFront']} | dead gallery {$stats['deadGallery']} | retryable {$stats['retryable']} | cursor {$cursor}");
            }
        }

        $summary = "products {$stats['products']} | warmed {$stats['warmed']} | dead front {$stats['deadFront']} | dead gallery {$stats['deadGallery']} | retryable {$stats['retryable']}";
        file_put_contents(config('cdn.state_dir', storage_path('app/cdn')) . '/warm.done', now()->toDateTimeString() . "\n" . $summary . "\n");
        $this->info('WARM COMPLETE: ' . $summary);

        return self::SUCCESS;
    }

    /**
     * HEAD every job URL. Returns [confirmed dead, retryable, warmed count, connection failures].
     *
     * @param  array<string, string>  $jobs
     * @return array{0: array<string, bool>, 1: array<string, string>, 2: int, 3: int}
     */
    private function probe(array $jobs): array
    {
        $dead = [];
        $retryable = [];
        $warmed = 0;
        $connFailures = 0;

        foreach (collect($jobs)->chunk($this->pool) as $chunk) {
            $responses = Http::pool(fn ($pool) => $chunk->map(
                fn ($url, $k) => $pool->as((string) $k)->connectTimeout(10)->timeout(45)->head($url)
            )->all());
            foreach ($chunk as $k => $url) {
                $resp = $responses[(string) $k] ?? null;
                if ($resp instanceof Response && $resp->successful()) {
                    $warmed++;
                } elseif ($resp instanceof Response && in_array($resp->status(), self::DEAD_STATUSES, true)) {
                    $dead[$k] = true;
                } elseif ($resp instanceof Response) {
                    // 5xx / 429 / anything else: the file may be fine — retry later
                    $retryable[$k] = $url;
                } else {
                    $retryable[$k] = $url;
                    $connFailures++;
                }
            }
        }

        return [$dead, $retryable, $warmed, $connFailures];
    }
}
