<?php

namespace App\Console\Commands;

use App\Models\Products;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class VerifyProductImages extends Command
{
    protected $signature = 'products:verify-images
        {--per-segment=30 : Alive images to confirm per homepage segment}
        {--scan-cap=3000 : Max rows to check per segment}';

    protected $description = 'Check front_image liveness for homepage segments, marking dead URLs in front_image_dead_at';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    private const POOL_SIZE = 25;

    public function handle(): int
    {
        $segments = [];
        foreach (Products::whereNotNull('body_style')->distinct()->pluck('body_style') as $style) {
            $segments[] = ['label' => "body:{$style}", 'wheres' => ['body_style' => $style]];

            // The body-type section interleaves makes, so each top make of
            // the segment needs a few verified-alive rows of its own.
            $topMakes = Products::query()
                ->where('body_style', $style)
                ->whereNotNull('make_id')
                ->whereNotNull('front_image')
                ->groupBy('make_id')
                ->selectRaw('make_id, COUNT(*) as c')
                ->orderByDesc('c')
                ->limit(5)
                ->pluck('make_id');
            foreach ($topMakes as $makeId) {
                $segments[] = [
                    'label' => "body:{$style}/make:{$makeId}",
                    'wheres' => ['body_style' => $style, 'make_id' => $makeId],
                    'target' => 4,
                    'cap' => 250,
                ];
            }
        }
        foreach (['Japan', 'China', 'Thailand'] as $country) {
            $segments[] = ['label' => "country:{$country}", 'wheres' => ['country' => $country]];
        }

        foreach ($segments as $segment) {
            $this->verifySegment($segment);
        }

        return self::SUCCESS;
    }

    private function verifySegment(array $segment): void
    {
        $target = $segment['target'] ?? (int) $this->option('per-segment');
        $cap = $segment['cap'] ?? (int) $this->option('scan-cap');
        $alive = 0;
        $dead = 0;
        $scanned = 0;
        $cursor = null;

        while ($alive < $target && $scanned < $cap) {
            $rows = Products::query()
                ->where($segment['wheres'])
                ->whereNotNull('front_image')
                ->whereNull('front_image_dead_at')
                ->when($cursor, fn ($q) => $q->where('created_at', '<', $cursor))
                ->orderByDesc('created_at')
                ->limit(self::POOL_SIZE)
                ->get(['id', 'front_image', 'created_at']);

            if ($rows->isEmpty()) {
                break;
            }

            $cursor = $rows->last()->getRawOriginal('created_at');
            $scanned += $rows->count();
            $deadIds = [];
            $remote = [];

            foreach ($rows as $row) {
                if (str_contains($row->front_image, 'product_images')) {
                    if (is_file(storage_path('app/public/' . $row->front_image))) {
                        $alive++;
                    } else {
                        $deadIds[] = $row->id;
                    }
                } else {
                    $remote[$row->id] = $row->front_image;
                }
            }

            if ($remote !== []) {
                $responses = Http::pool(fn ($pool) => collect($remote)->map(
                    fn ($url, $id) => $pool->as((string) $id)
                        ->withHeaders(['User-Agent' => self::USER_AGENT])
                        ->timeout(8)
                        ->head($url)
                )->all());

                foreach ($remote as $id => $url) {
                    $response = $responses[(string) $id] ?? null;
                    if ($response instanceof Response && $response->successful()) {
                        $alive++;
                    } elseif ($response instanceof Response) {
                        $deadIds[] = $id;
                    }
                    // Connection errors/timeouts: unknown, leave unmarked.
                }
            }

            if ($deadIds !== []) {
                $dead += count($deadIds);
                DB::table('products')->whereIn('id', $deadIds)->update(['front_image_dead_at' => now()]);
            }
        }

        $this->info(sprintf('%s: %d alive, %d dead, %d scanned', $segment['label'], $alive, $dead, $scanned));
    }
}
