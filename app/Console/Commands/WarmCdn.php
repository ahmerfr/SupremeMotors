<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WarmCdn extends Command
{
    protected $signature = 'products:warm-cdn {--start-id=0 : Resume from this product id}';

    protected $description = 'Request every CDN image once so Perma-Cache stores a permanent copy; marks fronts that 404 and drops dead gallery entries';

    private const POOL = 40;

    public function handle(): int
    {
        $cursor = (int) $this->option('start-id');
        $stats = ['products' => 0, 'warmed' => 0, 'deadFront' => 0, 'deadGallery' => 0, 'retryable' => 0];

        while (true) {
            $rows = DB::table('products')
                ->where('id', '>', $cursor)
                ->whereNull('front_image_dead_at')
                ->where(fn ($q) => $q
                    ->where('front_image', 'like', '%.b-cdn.net%')
                    ->orWhere('other_images', 'like', '%.b-cdn.net%'))
                ->orderBy('id')
                ->limit(200)
                ->get(['id', 'front_image', 'other_images']);
            if ($rows->isEmpty()) {
                break;
            }
            $cursor = $rows->last()->id;

            // flat list of jobs across the batch
            $jobs = [];
            foreach ($rows as $r) {
                if (str_contains((string) $r->front_image, '.b-cdn.net')) {
                    $jobs["{$r->id}|front"] = $r->front_image;
                }
                foreach (json_decode($r->other_images ?? '[]', true) ?: [] as $i => $url) {
                    if (is_string($url) && str_contains($url, '.b-cdn.net')) {
                        $jobs["{$r->id}|g{$i}"] = $url;
                    }
                }
            }

            $dead = [];
            foreach (collect($jobs)->chunk(self::POOL) as $chunk) {
                $responses = Http::pool(fn ($pool) => $chunk->map(
                    fn ($url, $k) => $pool->as((string) $k)->timeout(30)->head($url)
                )->all());
                foreach ($chunk as $k => $url) {
                    $resp = $responses[(string) $k] ?? null;
                    if ($resp instanceof Response && $resp->successful()) {
                        $stats['warmed']++;
                    } elseif ($resp instanceof Response) {
                        $dead[$k] = true;
                    } else {
                        $stats['retryable']++;
                    }
                }
            }

            // apply dead marks / gallery drops
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

            $stats['products'] += $rows->count();
            if ($stats['products'] % 5000 < 200) {
                $this->info("products {$stats['products']} | warmed {$stats['warmed']} | dead front {$stats['deadFront']} | dead gallery {$stats['deadGallery']} | retryable {$stats['retryable']} | cursor {$cursor}");
            }
        }

        $this->info("DONE: products {$stats['products']} | warmed {$stats['warmed']} | dead front {$stats['deadFront']} | dead gallery {$stats['deadGallery']} | retryable {$stats['retryable']}");
        return self::SUCCESS;
    }
}
