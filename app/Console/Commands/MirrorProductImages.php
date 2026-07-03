<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MirrorProductImages extends Command
{
    protected $signature = 'products:mirror-images
        {--limit=0 : Stop after this many mirrored images (0 = all)}
        {--website= : Only mirror one source website}';

    protected $description = 'Download every alive remote front image at original quality, upload to Bunny storage, point front_image at the CDN';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    private const BATCH = 20;

    public function handle(): int
    {
        $zone = config('services.bunny.storage_zone');
        $key = config('services.bunny.storage_key');
        $storageHost = config('services.bunny.storage_host');
        $cdnHost = config('services.bunny.cdn_host');

        if (!$zone || !$key || !$cdnHost) {
            $this->error('Set BUNNY_STORAGE_ZONE, BUNNY_STORAGE_KEY and BUNNY_CDN_HOST in .env first.');
            return self::FAILURE;
        }

        // Sanity: verify credentials before churning through the catalog.
        $probe = Http::withHeaders(['AccessKey' => $key])
            ->timeout(15)
            ->put("https://{$storageHost}/{$zone}/__connectivity_check.txt", 'ok');
        if (!$probe->successful()) {
            $this->error("Bunny storage rejected the test upload (HTTP {$probe->status()}). Check zone name / AccessKey / region host.");
            return self::FAILURE;
        }
        $this->info('Bunny storage connectivity OK.');

        $limit = (int) $this->option('limit');
        $cursor = 0;
        $mirrored = 0;
        $deadFound = 0;
        $failed = 0;

        while (true) {
            $rows = DB::table('products')
                ->where('id', '>', $cursor)
                ->where('front_image', 'like', 'http%')
                ->where('front_image', 'not like', "%{$cdnHost}%")
                ->whereNull('front_image_dead_at')
                ->when($this->option('website'), fn ($q, $w) => $q->where('website', $w))
                ->orderBy('id')
                ->limit(self::BATCH * 5)
                ->get(['id', 'front_image']);
            if ($rows->isEmpty()) {
                break;
            }
            $cursor = $rows->last()->id;

            foreach ($rows->chunk(self::BATCH) as $chunk) {
                // 1. download originals
                $downloads = Http::pool(fn ($pool) => $chunk->mapWithKeys(
                    fn ($r) => [(string) $r->id => $pool->as((string) $r->id)
                        ->withHeaders(['User-Agent' => self::USER_AGENT])->timeout(25)->get($r->front_image)]
                )->all());

                $toUpload = [];
                foreach ($chunk as $r) {
                    $resp = $downloads[(string) $r->id] ?? null;
                    if (!($resp instanceof Response)) {
                        $failed++; // connection error — retry on next run
                        continue;
                    }
                    if (!$resp->successful()) {
                        // died since the purge check
                        DB::table('products')->where('id', $r->id)->update(['front_image_dead_at' => now()]);
                        $deadFound++;
                        continue;
                    }
                    $ext = match (strtolower(explode(';', $resp->header('Content-Type'))[0])) {
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'image/gif' => 'gif',
                        default => 'jpg',
                    };
                    $toUpload[$r->id] = ['path' => "products/{$r->id}/front.{$ext}", 'body' => $resp->body(), 'source' => $r->front_image];
                }

                if ($toUpload === []) {
                    continue;
                }

                // 2. upload originals to Bunny storage
                $uploads = Http::pool(fn ($pool) => collect($toUpload)->mapWithKeys(
                    fn ($u, $id) => [(string) $id => $pool->as((string) $id)
                        ->withHeaders(['AccessKey' => $key, 'Content-Type' => 'application/octet-stream'])
                        ->timeout(60)
                        ->withBody($u['body'], 'application/octet-stream')
                        ->put("https://{$storageHost}/{$zone}/{$u['path']}")]
                )->all());

                // 3. swap DB links for confirmed uploads
                foreach ($toUpload as $id => $u) {
                    $resp = $uploads[(string) $id] ?? null;
                    if ($resp instanceof Response && $resp->successful()) {
                        DB::table('products')->where('id', $id)->update([
                            'front_image_source' => $u['source'],
                            'front_image' => "https://{$cdnHost}/{$u['path']}",
                        ]);
                        $mirrored++;
                    } else {
                        $failed++;
                    }
                }
            }

            if ($mirrored % 1000 < self::BATCH * 5 && $mirrored > 0) {
                $this->info("mirrored {$mirrored} | died since check {$deadFound} | failed (retryable) {$failed}");
            }
            if ($limit > 0 && $mirrored >= $limit) {
                break;
            }
        }

        $this->info("DONE: mirrored {$mirrored}, died since check {$deadFound}, failed {$failed} (re-run to retry failures)");
        return self::SUCCESS;
    }
}
