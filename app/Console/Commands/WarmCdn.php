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
        {--gallery-limit=0 : Warm at most this many gallery images per product (0 = all; e.g. 9 = front + first 9)}
        {--shard= : Named id-range worker; writes warm-<shard>.done instead of warm.done}
        {--min-id=0 : Shard range start (inclusive lower bound)}
        {--max-id= : Shard range end (exclusive upper bound)}
        {--website= : Warm only products from this source (e.g. autotrader)}
        {--pool= : concurrent HEADs (default 100)}
        {--timeout=45 : per-request timeout seconds (raise when the origin is throttled)}
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

    private int $timeoutSeconds = 45;

    public function handle(): int
    {
        $frontsOnly = $this->option('scope') === 'fronts';
        $galleryLimit = max(0, (int) $this->option('gallery-limit'));
        $shard = (string) $this->option('shard');
        $minId = (int) $this->option('min-id');
        $maxId = $this->option('max-id') !== null ? (int) $this->option('max-id') : null;
        $website = $this->option('website') ?: null;
        $this->pool = (int) ($this->option('pool') ?: self::POOL);
        $this->timeoutSeconds = max(15, (int) $this->option('timeout'));

        @mkdir(config('cdn.state_dir', storage_path('app/cdn')), 0777, true);
        $suffix = ($frontsOnly ? '-fronts' : '') . ($shard !== '' ? "-{$shard}" : '');
        $checkpointFile = config('cdn.state_dir', storage_path('app/cdn')) . "/warm{$suffix}.cursor";
        $retryLog = config('cdn.state_dir', storage_path('app/cdn')) . '/warm-retry.log';

        $cursor = $this->option('start-id') !== null
            ? (int) $this->option('start-id')
            : (int) @file_get_contents($checkpointFile);
        $cursor = max($cursor, $minId - 1);

        $maxOutageRetries = (int) $this->option('max-outage-retries');
        $stats = ['products' => 0, 'warmed' => 0, 'deadFront' => 0, 'deadGallery' => 0, 'retryable' => 0];
        $this->info('warming ' . ($frontsOnly ? 'FRONTS ONLY ' : '') . ($shard !== '' ? "shard {$shard} [{$minId}, " . ($maxId ?? '∞') . ') ' : '') . "from cursor {$cursor} (pool {$this->pool})");

        while (true) {
            $rows = DB::table('products')
                ->where('id', '>', $cursor)
                ->when($maxId !== null, fn ($q) => $q->where('id', '<', $maxId))
                ->when($website !== null, fn ($q) => $q->where('website', $website))
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
                    $g = 0;
                    foreach (json_decode($r->other_images ?? '[]', true) ?: [] as $i => $url) {
                        if ($galleryLimit > 0 && $g >= $galleryLimit) {
                            break; // cap gallery images warmed per car (front + N)
                        }
                        if (is_string($url) && str_contains($url, '.b-cdn.net')) {
                            $jobs["{$r->id}|g{$i}"] = $url;
                            $g++;
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
        // A shard finishing only proves its range; the coordinator writes
        // warm.done once every shard's marker exists.
        $doneFile = $shard !== '' ? "warm-{$shard}.done" : 'warm.done';
        file_put_contents(config('cdn.state_dir', storage_path('app/cdn')) . '/' . $doneFile, now()->toDateTimeString() . "\n" . $summary . "\n");
        $this->info('WARM COMPLETE' . ($shard !== '' ? " (shard {$shard})" : '') . ': ' . $summary);

        return self::SUCCESS;
    }

    /**
     * HEAD every job URL. Returns [confirmed dead, retryable, warmed count, connection failures].
     *
     * Chunked pools wait on their slowest member (head-of-line blocking), so a
     * long timeout lets one straggler hold a whole chunk hostage. Two passes:
     * a fast pass with a short cap clears the quick majority, then only the
     * stragglers get the full configured timeout.
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

        $fastTimeout = min(20, $this->timeoutSeconds);
        $stragglers = [];

        $classify = function (array $batch, int $timeout, bool $collectStragglers) use (&$dead, &$retryable, &$warmed, &$connFailures, &$stragglers): void {
            foreach (collect($batch)->chunk($this->pool) as $chunk) {
                $responses = Http::pool(fn ($pool) => $chunk->map(
                    fn ($url, $k) => $pool->as((string) $k)->connectTimeout(10)->timeout($timeout)->head($url)
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
                    } elseif ($collectStragglers) {
                        $stragglers[$k] = $url;
                    } else {
                        $retryable[$k] = $url;
                        $connFailures++;
                    }
                }
            }
        };

        // Rolling window in production: keep the pool full continuously —
        // chunked pools idle 60-80% of the time waiting on each chunk's
        // slowest member. Tests keep the Http::fake-able chunked path.
        if (! app()->runningUnitTests()) {
            $client = new \GuzzleHttp\Client([
                'connect_timeout' => 10,
                'timeout' => $this->timeoutSeconds,
                'http_errors' => false,
            ]);
            $requests = function () use ($jobs) {
                foreach ($jobs as $k => $url) {
                    yield (string) $k => new \GuzzleHttp\Psr7\Request('HEAD', $url);
                }
            };
            $guzzlePool = new \GuzzleHttp\Pool($client, $requests(), [
                'concurrency' => $this->pool,
                'fulfilled' => function ($resp, $k) use (&$dead, &$retryable, &$warmed, $jobs) {
                    $code = $resp->getStatusCode();
                    if ($code >= 200 && $code < 300) {
                        $warmed++;
                    } elseif (in_array($code, self::DEAD_STATUSES, true)) {
                        $dead[$k] = true;
                    } else {
                        $retryable[$k] = $jobs[$k];
                    }
                },
                'rejected' => function ($e, $k) use (&$retryable, &$connFailures, $jobs) {
                    $retryable[$k] = $jobs[$k];
                    $connFailures++;
                },
            ]);
            $guzzlePool->promise()->wait();

            return [$dead, $retryable, $warmed, $connFailures];
        }

        $classify($jobs, $fastTimeout, $this->timeoutSeconds > $fastTimeout);
        if ($stragglers !== []) {
            $classify($stragglers, $this->timeoutSeconds, false);
        }

        return [$dead, $retryable, $warmed, $connFailures];
    }
}
