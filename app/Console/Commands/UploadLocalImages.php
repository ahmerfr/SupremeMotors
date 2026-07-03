<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class UploadLocalImages extends Command
{
    protected $signature = 'products:upload-local-images
        {--pool=24 : concurrent uploads}
        {--dir=product_images : folder under storage/app/public to mirror}
        {--skip-upload : only run the URL swap}
        {--swap : rewrite product rows to the CDN URLs after uploading}';

    protected $description = 'Upload admin-uploaded local product images to the Bunny storage zone and point product rows at sm-media.b-cdn.net';

    private const STORAGE_ZONE_ID = 1630956;

    public const CDN = 'https://sm-media.b-cdn.net';

    public function handle(): int
    {
        $dir = $this->option('dir');

        if (! $this->option('skip-upload')) {
            [$zoneName, $password] = $this->storageZoneCredentials();
            if (! $password) {
                $this->error('could not resolve storage zone credentials');

                return self::FAILURE;
            }
            $this->uploadDirectory($dir, $zoneName, $password);
        }

        if ($this->option('swap')) {
            $this->swap($dir);
        }

        return self::SUCCESS;
    }

    /** @return array{0: ?string, 1: ?string} [zone name, password] */
    private function storageZoneCredentials(): array
    {
        $zone = Http::withHeaders(['AccessKey' => config('services.bunny.account_key')])
            ->get('https://api.bunny.net/storagezone/' . self::STORAGE_ZONE_ID)
            ->json();

        return [$zone['Name'] ?? null, $zone['Password'] ?? null];
    }

    private function uploadDirectory(string $dir, string $zoneName, string $password): void
    {
        $local = storage_path('app/public/' . $dir);
        $files = collect(is_dir($local) ? scandir($local) : [])
            ->filter(fn ($f) => is_file($local . DIRECTORY_SEPARATOR . $f))
            ->values();

        // resume support: skip whatever already made it to the zone
        $remote = collect(
            Http::withHeaders(['AccessKey' => $password])
                ->get("https://storage.bunnycdn.com/{$zoneName}/{$dir}/")
                ->json() ?? []
        )->pluck('ObjectName')->flip();

        $pending = $files->reject(fn ($f) => $remote->has($f))->values();
        $this->info("{$files->count()} local files, {$remote->count()} already uploaded, {$pending->count()} to go");

        $pool = max(1, (int) $this->option('pool'));
        $done = 0;
        $failed = [];

        foreach ($pending->chunk($pool) as $chunk) {
            $responses = Http::pool(fn ($p) => $chunk->map(
                fn ($file) => $p->as($file)
                    ->withHeaders(['AccessKey' => $password, 'Content-Type' => 'application/octet-stream'])
                    ->timeout(120)
                    ->withBody(file_get_contents($local . DIRECTORY_SEPARATOR . $file), 'application/octet-stream')
                    ->put("https://storage.bunnycdn.com/{$zoneName}/{$dir}/" . rawurlencode($file))
            )->all());

            foreach ($chunk as $file) {
                $r = $responses[$file] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->successful()) {
                    $done++;
                } else {
                    $failed[] = $file;
                }
            }
            if ($done % 480 < $pool) {
                $this->info("uploaded {$done}/{$pending->count()}");
            }
        }

        // one retry round for stragglers
        foreach ($failed as $i => $file) {
            $r = Http::withHeaders(['AccessKey' => $password, 'Content-Type' => 'application/octet-stream'])
                ->timeout(120)
                ->withBody(file_get_contents($local . DIRECTORY_SEPARATOR . $file), 'application/octet-stream')
                ->put("https://storage.bunnycdn.com/{$zoneName}/{$dir}/" . rawurlencode($file));
            if ($r->successful()) {
                unset($failed[$i]);
            }
        }

        $this->info("upload complete: {$done} uploaded, " . count($failed) . ' failed');
        foreach ($failed as $file) {
            $this->warn("failed: {$file}");
        }
    }

    private function swap(string $dir): void
    {
        $prefix = $dir . '/';
        $cdnPrefix = self::CDN . '/' . $prefix;

        // ~1K rows — row-wise keeps the JSON handling exact and works on any driver.
        $fronts = 0;
        $galleries = 0;
        DB::table('products')
            ->select('id', 'front_image', 'front_image_source', 'other_images', 'other_images_source')
            ->where(function ($q) use ($prefix, $dir) {
                $q->where('front_image', 'like', $prefix . '%')
                    ->orWhere('other_images', 'like', '%' . $dir . '%');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$fronts, &$galleries, $prefix, $cdnPrefix) {
                foreach ($rows as $row) {
                    $update = [];

                    if (is_string($row->front_image) && str_starts_with($row->front_image, $prefix)) {
                        $update['front_image_source'] = $row->front_image_source ?? $row->front_image;
                        $update['front_image'] = self::CDN . '/' . $row->front_image;
                        $fronts++;
                    }

                    $images = json_decode($row->other_images ?? '', true);
                    if (is_array($images)) {
                        $changed = false;
                        $mapped = array_map(function ($img) use (&$changed, $prefix, $cdnPrefix) {
                            if (is_string($img) && str_starts_with($img, $prefix)) {
                                $changed = true;

                                return $cdnPrefix . substr($img, strlen($prefix));
                            }

                            return $img;
                        }, $images);

                        if ($changed) {
                            $update['other_images_source'] = $row->other_images_source ?? $row->other_images;
                            $update['other_images'] = json_encode($mapped, JSON_UNESCAPED_SLASHES);
                            $galleries++;
                        }
                    }

                    if ($update) {
                        DB::table('products')->where('id', $row->id)->update($update);
                    }
                }
            });

        Cache::flush();
        $this->info("swapped fronts: {$fronts}, galleries: {$galleries}; caches cleared");
    }
}
