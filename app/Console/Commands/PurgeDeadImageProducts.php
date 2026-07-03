<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PurgeDeadImageProducts extends Command
{
    protected $signature = 'products:purge-dead-images
        {--check-only : Verify and mark, but delete nothing}
        {--gallery-probe=3 : How many other_images to try before declaring a product imageless}';

    protected $description = 'Verify every product front image, rescue products via alive gallery images, delete products with no working image at all';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    private const POOL = 40;

    public function handle(): int
    {
        $this->checkFrontImages();
        [$rescued, $doomed] = $this->rescueOrCondemn();

        // Products with no front image at all were never sellable either.
        $emptyIds = DB::table('products')
            ->where(fn ($q) => $q->whereNull('front_image')->orWhere('front_image', ''))
            ->pluck('id')
            ->all();
        $doomed = array_merge($doomed, $emptyIds);

        $this->info('rescued via gallery image: ' . $rescued);
        $this->info('to delete (no working image): ' . count($doomed));

        if ($this->option('check-only')) {
            $this->info('check-only: nothing deleted');
            return self::SUCCESS;
        }

        // per-site breakdown before deleting
        foreach (array_chunk($doomed, 10000) as $chunk) {
            $rows = DB::table('products')->whereIn('id', $chunk)
                ->groupBy('website')->selectRaw('website, COUNT(*) c')->pluck('c', 'website');
            foreach ($rows as $site => $c) {
                $tally[$site] = ($tally[$site] ?? 0) + $c;
            }
        }
        foreach ($tally ?? [] as $site => $c) {
            $this->info("delete {$site}: {$c}");
        }

        $deleted = 0;
        foreach (array_chunk($doomed, 5000) as $chunk) {
            $deleted += DB::table('products')->whereIn('id', $chunk)->delete();
        }
        $this->info("deleted: {$deleted}");
        $this->info('remaining products: ' . DB::table('products')->count());

        Artisan::call('cache:clear');
        $this->info('caches cleared');

        return self::SUCCESS;
    }

    /** HEAD-check every unchecked front image; mark 404s. */
    private function checkFrontImages(): void
    {
        $cursor = 0;
        $checked = 0;
        $dead = 0;

        while (true) {
            $rows = DB::table('products')
                ->where('id', '>', $cursor)
                ->whereNotNull('front_image')
                ->where('front_image', '!=', '')
                ->whereNull('front_image_dead_at')
                ->orderBy('id')
                ->limit(1000)
                ->get(['id', 'front_image']);
            if ($rows->isEmpty()) {
                break;
            }
            $cursor = $rows->last()->id;

            $local = $rows->filter(fn ($r) => !str_starts_with($r->front_image, 'http'));
            $deadIds = [];
            foreach ($local as $r) {
                if (!is_file(storage_path('app/public/' . $r->front_image))) {
                    $deadIds[] = $r->id;
                }
            }

            $remote = $rows->filter(fn ($r) => str_starts_with($r->front_image, 'http'));
            foreach ($remote->chunk(self::POOL) as $chunk) {
                $responses = Http::pool(fn ($pool) => $chunk->mapWithKeys(
                    fn ($r) => [(string) $r->id => $pool->as((string) $r->id)
                        ->withHeaders(['User-Agent' => self::USER_AGENT])->timeout(8)->head($r->front_image)]
                )->all());
                foreach ($chunk as $r) {
                    $resp = $responses[(string) $r->id] ?? null;
                    if ($resp instanceof Response && !$resp->successful()) {
                        $deadIds[] = $r->id;
                    }
                    // alive or connection error: leave unmarked (never delete unknowns)
                }
            }

            if ($deadIds !== []) {
                DB::table('products')->whereIn('id', $deadIds)->update(['front_image_dead_at' => now()]);
                $dead += count($deadIds);
            }
            $checked += $rows->count();
            if ($checked % 20000 < 1000) {
                $this->info("front check: {$checked} checked, {$dead} newly dead");
            }
        }
        $this->info("front check done: {$checked} checked, {$dead} newly dead");
    }

    /**
     * For every dead-front product, probe the first N gallery images. First
     * alive one is promoted to front_image; products with none go on the
     * delete list.
     */
    private function rescueOrCondemn(): array
    {
        $probe = (int) $this->option('gallery-probe');
        $cursor = 0;
        $rescued = 0;
        $doomed = [];
        $processed = 0;

        while (true) {
            $rows = DB::table('products')
                ->where('id', '>', $cursor)
                ->whereNotNull('front_image_dead_at')
                ->orderBy('id')
                ->limit(500)
                ->get(['id', 'other_images']);
            if ($rows->isEmpty()) {
                break;
            }
            $cursor = $rows->last()->id;

            // candidate gallery URLs per product
            $candidates = [];
            foreach ($rows as $r) {
                $imgs = json_decode($r->other_images ?? '[]', true) ?: [];
                $urls = array_values(array_filter($imgs, fn ($u) => is_string($u) && str_starts_with($u, 'http')));
                $candidates[$r->id] = array_slice($urls, 0, $probe);
            }

            // probe round by round so most products need only one request
            $alive = [];
            for ($roundNo = 0; $roundNo < $probe; $roundNo++) {
                $pending = collect($candidates)
                    ->filter(fn ($urls, $id) => !isset($alive[$id]) && isset($urls[$roundNo]))
                    ->map(fn ($urls) => $urls[$roundNo]);
                if ($pending->isEmpty()) {
                    continue;
                }
                foreach ($pending->chunk(self::POOL) as $chunk) {
                    $responses = Http::pool(fn ($pool) => $chunk->map(
                        fn ($url, $id) => $pool->as((string) $id)
                            ->withHeaders(['User-Agent' => self::USER_AGENT])->timeout(8)->head($url)
                    )->all());
                    foreach ($chunk as $id => $url) {
                        $resp = $responses[(string) $id] ?? null;
                        if ($resp instanceof Response && $resp->successful()) {
                            $alive[$id] = $url;
                        }
                    }
                }
            }

            foreach ($rows as $r) {
                if (isset($alive[$r->id])) {
                    DB::table('products')->where('id', $r->id)
                        ->update(['front_image' => $alive[$r->id], 'front_image_dead_at' => null]);
                    $rescued++;
                } else {
                    $doomed[] = $r->id;
                }
            }

            $processed += $rows->count();
            if ($processed % 10000 < 500) {
                $this->info("gallery rescue: {$processed} processed, {$rescued} rescued, " . count($doomed) . ' doomed');
            }
        }

        return [$rescued, $doomed];
    }
}
