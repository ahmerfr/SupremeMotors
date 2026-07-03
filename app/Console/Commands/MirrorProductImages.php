<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MirrorProductImages extends Command
{
    protected $signature = 'products:mirror-images
        {--limit=0 : Stop after this many products (0 = all)}
        {--website= : Only mirror one source website}';

    protected $description = 'Download every alive remote image (front + gallery) at original quality, upload to Bunny storage, point the DB at the CDN';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    private const POOL = 40;

    private string $zone;
    private string $key;
    private string $storageHost;
    private string $cdnHost;

    public function handle(): int
    {
        $this->zone = (string) config('services.bunny.storage_zone');
        $this->key = (string) config('services.bunny.storage_key');
        $this->storageHost = (string) config('services.bunny.storage_host');
        $this->cdnHost = (string) config('services.bunny.cdn_host');

        if (!$this->zone || !$this->key || !$this->cdnHost) {
            $this->error('Set BUNNY_STORAGE_ZONE, BUNNY_STORAGE_KEY and BUNNY_CDN_HOST in .env first.');
            return self::FAILURE;
        }

        // Sanity: verify credentials before churning through the catalog.
        $probe = Http::withHeaders(['AccessKey' => $this->key])
            ->timeout(15)
            ->put("https://{$this->storageHost}/{$this->zone}/__connectivity_check.txt", 'ok');
        if (!$probe->successful()) {
            $this->error("Bunny storage rejected the test upload (HTTP {$probe->status()}). Check zone name / AccessKey / region host.");
            return self::FAILURE;
        }
        $this->info('Bunny storage connectivity OK.');

        $limit = (int) $this->option('limit');
        $cursor = 0;
        $products = 0;
        $stats = ['mirrored' => 0, 'dead' => 0, 'retryable' => 0];

        while (true) {
            $rows = DB::table('products')
                ->where('id', '>', $cursor)
                ->whereNull('front_image_dead_at')
                ->where(fn ($q) => $q
                    ->where(fn ($qq) => $qq->where('front_image', 'like', 'http%')
                        ->where('front_image', 'not like', "%{$this->cdnHost}%"))
                    ->orWhere('other_images', 'like', '%http%'))
                ->when($this->option('website'), fn ($q, $w) => $q->where('website', $w))
                ->orderBy('id')
                ->limit(50)
                ->get(['id', 'front_image', 'other_images', 'front_image_source', 'other_images_source']);
            if ($rows->isEmpty()) {
                break;
            }
            $cursor = $rows->last()->id;

            foreach ($rows as $row) {
                if ($this->mirrorProduct($row, $stats)) {
                    $products++;
                }
                if ($limit > 0 && $products >= $limit) {
                    break 2;
                }
            }

            if ($products > 0 && $products % 500 < 50) {
                $this->info("products {$products} | images mirrored {$stats['mirrored']} | dead {$stats['dead']} | retryable {$stats['retryable']}");
            }
        }

        $this->info("DONE: {$products} products | images mirrored {$stats['mirrored']} | dead {$stats['dead']} | retryable {$stats['retryable']} (re-run to retry)");
        return self::SUCCESS;
    }

    /**
     * Mirror the front image and every gallery image of one product.
     * Returns true if anything was mirrored.
     */
    private function mirrorProduct(object $row, array &$stats): bool
    {
        $jobs = []; // key => ['url' =>, 'path' =>]

        $frontNeedsMirror = str_starts_with((string) $row->front_image, 'http')
            && !str_contains((string) $row->front_image, $this->cdnHost);
        if ($frontNeedsMirror) {
            $jobs['front'] = ['url' => $row->front_image];
        }

        $gallery = json_decode($row->other_images ?? '[]', true) ?: [];
        foreach ($gallery as $i => $url) {
            if (is_string($url) && str_starts_with($url, 'http') && !str_contains($url, $this->cdnHost)) {
                $jobs["g{$i}"] = ['url' => $url];
            }
        }

        if ($jobs === []) {
            return false;
        }

        // 1. download originals in pooled slices
        $bodies = [];
        $deadKeys = [];
        foreach (array_chunk($jobs, self::POOL, true) as $slice) {
            $responses = Http::pool(fn ($pool) => collect($slice)->map(
                fn ($job, $k) => $pool->as((string) $k)
                    ->withHeaders(['User-Agent' => self::USER_AGENT])->timeout(25)->get($job['url'])
            )->all());
            foreach ($slice as $k => $job) {
                $resp = $responses[(string) $k] ?? null;
                if ($resp instanceof Response && $resp->successful()) {
                    $ext = match (strtolower(explode(';', (string) $resp->header('Content-Type'))[0])) {
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'image/gif' => 'gif',
                        default => 'jpg',
                    };
                    $bodies[$k] = ['body' => $resp->body(), 'path' => "products/{$row->id}/{$k}.{$ext}"];
                } elseif ($resp instanceof Response) {
                    $deadKeys[] = $k; // 4xx/5xx: image is gone
                    $stats['dead']++;
                } else {
                    $stats['retryable']++; // connection error: keep original URL, retry later
                }
            }
        }

        // 2. upload to Bunny
        $uploaded = [];
        foreach (array_chunk($bodies, self::POOL, true) as $slice) {
            $responses = Http::pool(fn ($pool) => collect($slice)->map(
                fn ($u, $k) => $pool->as((string) $k)
                    ->withHeaders(['AccessKey' => $this->key])
                    ->timeout(60)
                    ->withBody($u['body'], 'application/octet-stream')
                    ->put("https://{$this->storageHost}/{$this->zone}/{$u['path']}")
            )->all());
            foreach ($slice as $k => $u) {
                $resp = $responses[(string) $k] ?? null;
                if ($resp instanceof Response && $resp->successful()) {
                    $uploaded[$k] = "https://{$this->cdnHost}/{$u['path']}";
                    $stats['mirrored']++;
                } else {
                    $stats['retryable']++;
                }
            }
        }

        if ($uploaded === [] && $deadKeys === []) {
            return false;
        }

        // 3. swap the DB record
        $update = [];
        if ($frontNeedsMirror && isset($uploaded['front'])) {
            $update['front_image'] = $uploaded['front'];
            $update['front_image_source'] = $row->front_image_source ?: $row->front_image;
        } elseif ($frontNeedsMirror && in_array('front', $deadKeys, true)) {
            $update['front_image_dead_at'] = now();
        }

        $newGallery = [];
        $galleryChanged = false;
        foreach ($gallery as $i => $url) {
            $k = "g{$i}";
            if (isset($uploaded[$k])) {
                $newGallery[] = $uploaded[$k];
                $galleryChanged = true;
            } elseif (in_array($k, $deadKeys, true)) {
                $galleryChanged = true; // drop dead image from the gallery
            } else {
                $newGallery[] = $url; // untouched (already CDN, local, or retryable)
            }
        }
        if ($galleryChanged) {
            $update['other_images'] = json_encode(array_values($newGallery));
            $update['other_images_source'] = $row->other_images_source ?: $row->other_images;
        }

        if ($update !== []) {
            DB::table('products')->where('id', $row->id)->update($update);
        }

        return $uploaded !== [];
    }
}
