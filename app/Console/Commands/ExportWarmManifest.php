<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Export the list of images to warm (front + N gallery per car) as SHARDED
 * manifest files, one line per image `<size>/<hash>`, round-robined across
 * --shards files. A GitHub Actions matrix then runs one runner per shard, so
 * ~20 different egress IPs download from m.atcdn in parallel (beating the
 * single-IP origin cap) and PUT straight into Bunny's perma-cache storage.
 * The runners need only these flat files — no DB access.
 */
class ExportWarmManifest extends Command
{
    protected $signature = 'products:export-warm-manifest
        {--website=autotraderuk : source to export}
        {--gallery-limit=9 : front + this many gallery images per car}
        {--shards=20 : number of shard files (= parallel GitHub runners)}
        {--out= : output dir (default .github/warm-manifest)}';

    protected $description = 'Export sharded image manifest (<size>/<hash> per line) for the GitHub Actions gallery warmer';

    public function handle(): int
    {
        $website = (string) $this->option('website');
        $galleryLimit = max(0, (int) $this->option('gallery-limit'));
        $shards = max(1, (int) $this->option('shards'));
        $out = $this->option('out') ?: base_path('.github/warm-manifest');
        @mkdir($out, 0777, true);

        // open a handle per shard
        $fh = [];
        for ($i = 1; $i <= $shards; $i++) {
            $fh[$i] = fopen(sprintf('%s/%s-%02d.txt', $out, $website, $i), 'w');
        }

        $seen = [];
        $total = 0;
        $cursor = 0;
        $s = 1;
        while (true) {
            $rows = DB::table('products')->where('website', $website)
                ->where('id', '>', $cursor)->orderBy('id')->limit(2000)
                ->get(['id', 'front_image', 'other_images']);
            if ($rows->isEmpty()) {
                break;
            }
            foreach ($rows as $r) {
                $cursor = (int) $r->id;
                $urls = [];
                if ($r->front_image) {
                    $urls[] = $r->front_image;
                }
                $g = 0;
                foreach ((array) json_decode($r->other_images ?? '[]', true) as $u) {
                    if ($galleryLimit > 0 && $g >= $galleryLimit) {
                        break;
                    }
                    if (is_string($u)) {
                        $urls[] = $u;
                        $g++;
                    }
                }
                foreach ($urls as $u) {
                    if (preg_match('#/a/media/([^/]+)/([0-9a-f]{32})#i', $u, $m)) {
                        $key = $m[1] . '/' . strtolower($m[2]);
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;
                        fwrite($fh[$s], $key . "\n");
                        $s = $s % $shards + 1;   // round-robin across shards
                        $total++;
                    }
                }
            }
            if ($total % 100000 < 20) {
                $this->info("exported {$total} images (cursor {$cursor})...");
            }
        }
        foreach ($fh as $h) {
            fclose($h);
        }
        $this->info("DONE: {$total} unique images across {$shards} shards in {$out}");

        return self::SUCCESS;
    }
}
