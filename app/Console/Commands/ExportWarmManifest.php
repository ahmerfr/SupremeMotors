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
        {--limit=0 : cap total rows scanned (0 = all; for sampling/testing)}
        {--out= : output dir (default .github/warm-manifest)}';

    protected $description = 'Export sharded image manifest (<size>/<hash> per line) for the GitHub Actions gallery warmer';

    public function handle(): int
    {
        $website = (string) $this->option('website');
        $galleryLimit = max(0, (int) $this->option('gallery-limit'));
        $shards = max(1, (int) $this->option('shards'));
        $rowLimit = max(0, (int) $this->option('limit'));
        $rowsScanned = 0;
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
                $rowsScanned++;
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
                    $key = null;
                    if ($website === 'jaftim') {
                        // jaftim: full storage path incl. extension, host-agnostic.
                        // Both erp.jaftim.com and sm-jaftim.b-cdn.net share the same
                        // path shape: /storage/app/public/stock/<id>/<file.ext>.
                        // Extension varies (jpg/jpeg/png/webp/avif) so we keep the
                        // literal filename+ext — the perma-cache path must be exact.
                        if (preg_match('~(storage/app/public/stock/[0-9]+/[^/?#]+\.[a-z0-9]+)~i', $u, $m)) {
                            $key = $m[1];
                        }
                    } elseif (preg_match('#/a/media/([^/]+)/([0-9a-f]{32})#i', $u, $m)) {
                        // autotrader: <size>/<hash>, .jpg extension appended by warmer
                        $key = $m[1] . '/' . strtolower($m[2]);
                    }
                    if ($key === null) {
                        continue;
                    }
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    fwrite($fh[$s], $key . "\n");
                    $s = $s % $shards + 1;   // round-robin across shards
                    $total++;
                }
                if ($rowLimit > 0 && $rowsScanned >= $rowLimit) {
                    break 2;
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
