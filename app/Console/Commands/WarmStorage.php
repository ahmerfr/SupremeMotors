<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pre-warm Bunny's perma-cache by writing images STRAIGHT INTO its storage zone
 * — bypassing the slow "Bunny pulls from origin on demand" path entirely.
 *
 * WHY: a normal warm (GET the b-cdn URL) makes Bunny pull each cold image from
 * the m.atcdn origin, which caps ~30-40 img/s from one IP and *breaks* (serves-
 * but-doesn't-persist) if you push harder. Instead we:
 *   1. DOWNLOAD each image from m.atcdn ourselves, spread across a pool of free
 *      PROXIES (many egress IPs -> beats the per-IP origin cap), and
 *   2. PUT it into the pull-zone's perma-cache storage path
 *        <storagezone>/__bcdn_perma_cache__/pullzone__<zone>__<id>/a/media/<size>/<hash>.jpg
 *      (proven: a PUT there makes the pull-zone serve the object CDN-Cache: HIT
 *      with no origin fetch).
 *
 * All HTTP is done with the Windows curl.exe (Schannel) — PHP's OpenSSL libcurl
 * hangs on m.atcdn/Bunny. Resumable per --shard cursor; nothing is re-fetched.
 */
class WarmStorage extends Command
{
    protected $signature = 'products:warm-storage
        {--website=autotraderuk : Source to warm}
        {--pullzone-dir= : perma-cache dir, e.g. pullzone__sm-autotraderuk__56344427 (required)}
        {--storage-zone=suprememotors-media : Bunny storage zone name backing the perma-cache}
        {--min-id=0 : shard range start}
        {--max-id= : shard range end}
        {--shard= : names the cursor + done marker}
        {--gallery-limit=9 : warm front + this many gallery images per car}
        {--pool=60 : concurrent transfers}
        {--proxy-file= : file of ip:port proxies for the m.atcdn downloads (required for speed)}
        {--curl-bin=C:\Windows\System32\curl.exe : Schannel curl}';

    protected $description = 'Warm Bunny perma-cache by proxy-downloading images and writing them into the storage zone directly';

    private const ORIGIN = 'https://m.atcdn.co.uk';

    private const STORAGE_API = 'https://storage.bunnycdn.com';

    private string $curl;

    private string $storagePw = '';

    /** @var string[] */
    private array $proxies = [];

    private int $px = 0;

    private string $tmpDir;

    public function handle(): int
    {
        $this->curl = (string) $this->option('curl-bin');
        $pzDir = (string) $this->option('pullzone-dir');
        if ($pzDir === '') {
            $this->error('--pullzone-dir is required (e.g. pullzone__sm-autotraderuk__56344427)');

            return self::FAILURE;
        }
        $zone = (string) $this->option('storage-zone');
        $galleryLimit = max(0, (int) $this->option('gallery-limit'));
        $pool = max(4, (int) $this->option('pool'));
        $website = (string) $this->option('website');
        $minId = (int) $this->option('min-id');
        $maxId = $this->option('max-id') !== null ? (int) $this->option('max-id') : null;

        $stateDir = config('cdn.state_dir', storage_path('app/cdn'));
        @mkdir($stateDir, 0777, true);
        $this->tmpDir = $stateDir . '/warmstore-' . ($this->option('shard') ?: 'x');
        @mkdir($this->tmpDir, 0777, true);
        $shard = $this->option('shard') ? '-' . $this->option('shard') : '';
        $cursorFile = $stateDir . "/warmstore{$shard}.cursor";
        $doneFile = $stateDir . "/warmstore{$shard}.done";
        @unlink($doneFile);

        $this->storagePw = $this->fetchStoragePassword($zone);
        if ($this->storagePw === '') {
            $this->error('could not resolve the storage-zone write password from the Bunny API');

            return self::FAILURE;
        }

        $this->loadProxies();
        $this->info('warm-storage ' . $website . ' via ' . count($this->proxies) . ' proxies (pool ' . $pool . ') into ' . $zone . '/' . $pzDir);

        $cursor = is_file($cursorFile) ? (int) file_get_contents($cursorFile) : ($minId > 0 ? $minId - 1 : 0);
        $warmed = 0;
        $failed = 0;

        while (true) {
            $rows = DB::table('products')
                ->where('website', $website)
                ->where('id', '>', $cursor)
                ->when($maxId !== null, fn ($q) => $q->where('id', '<', $maxId))
                ->orderBy('id')
                ->limit(150)
                ->get(['id', 'front_image', 'other_images']);
            if ($rows->isEmpty()) {
                break;
            }

            // collect (size, hash) for every image URL, capped per car
            $jobs = [];
            foreach ($rows as $r) {
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
                        $jobs[$m[1] . '/' . strtolower($m[2])] = ['size' => $m[1], 'hash' => strtolower($m[2])];
                    }
                }
                $cursor = (int) $r->id;
            }

            [$w, $f] = $this->warmBatch(array_values($jobs), $pzDir, $zone, $pool);
            $warmed += $w;
            $failed += $f;
            file_put_contents($cursorFile, (string) $cursor);
            $this->info("cursor {$cursor} | warmed {$warmed} | failed {$failed} | proxies " . count($this->proxies));
        }

        file_put_contents($doneFile, now()->toDateTimeString());
        $this->info("WARM-STORAGE COMPLETE{$shard}: warmed {$warmed}, failed {$failed}");
        @rmdir($this->tmpDir);

        return self::SUCCESS;
    }

    /**
     * Two-wave batch: proxy-download every image to a temp file, then PUT the
     * non-empty ones into the storage zone. Returns [warmed, failed].
     *
     * @param  array<int,array{size:string,hash:string}>  $jobs
     * @return array{0:int,1:int}
     */
    private function warmBatch(array $jobs, string $pzDir, string $zone, int $pool): array
    {
        // wave 1 — download from m.atcdn through rotating proxies
        $dl = [];
        foreach ($jobs as $j) {
            $tmp = $this->tmpDir . '/' . $j['size'] . '_' . $j['hash'];
            $origin = self::ORIGIN . '/a/media/' . $j['size'] . '/' . $j['hash'] . '.jpg';
            $args = ['-s', '--max-time', '25', '-o', $tmp, '-A', 'Mozilla/5.0'];
            if ($this->proxies) {
                $args[] = '-x';
                $args[] = 'http://' . $this->nextProxy();
            }
            $args[] = $origin;
            $dl[] = ['args' => $args, 'tmp' => $tmp, 'job' => $j];
        }
        $this->runPool(array_column($dl, 'args'), $pool);

        // wave 2 — PUT the downloaded files into the perma-cache storage path
        $put = [];
        $warmed = 0;
        $failed = 0;
        foreach ($dl as $d) {
            if (is_file($d['tmp']) && filesize($d['tmp']) > 1000) {
                $j = $d['job'];
                $dest = self::STORAGE_API . '/' . $zone . '/__bcdn_perma_cache__/' . $pzDir . '/a/media/' . $j['size'] . '/' . $j['hash'] . '.jpg';
                $put[] = ['-s', '-o', 'NUL', '-X', 'PUT', '--max-time', '25',
                    '-H', 'AccessKey: ' . $this->storagePw, '-H', 'Content-Type: image/jpeg',
                    '--data-binary', '@' . $d['tmp'], $dest];
                $warmed++;
            } else {
                $failed++;
            }
        }
        $this->runPool($put, $pool);

        foreach ($dl as $d) {
            @unlink($d['tmp']);
        }

        return [$warmed, $failed];
    }

    /**
     * Run a rolling pool of curl.exe processes (each an argv array).
     *
     * @param  array<int,array<string>>  $commands
     */
    private function runPool(array $commands, int $poolMax): void
    {
        $queue = $commands;
        $running = [];
        while ($queue !== [] || $running !== []) {
            while ($queue !== [] && count($running) < $poolMax) {
                $args = array_shift($queue);
                $cmd = $this->esc($this->curl);
                foreach ($args as $a) {
                    $cmd .= ' ' . $this->esc($a);
                }
                $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (is_resource($p)) {
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $running[] = $p;
                }
            }
            foreach ($running as $i => $p) {
                if (!proc_get_status($p)['running']) {
                    proc_close($p);
                    unset($running[$i]);
                }
            }
            if ($running !== []) {
                usleep(15000);
            }
        }
    }

    private function nextProxy(): string
    {
        $p = $this->proxies[$this->px % count($this->proxies)];
        $this->px++;

        return $p;
    }

    private function loadProxies(): void
    {
        $f = (string) $this->option('proxy-file');
        if ($f !== '' && is_file($f)) {
            $this->proxies = array_values(array_filter(array_map('trim', file($f)), fn ($l) => $l !== '' && $l[0] !== '#'));
        }
    }

    /** resolve the storage-zone write password via the Bunny account API (curl.exe) */
    private function fetchStoragePassword(string $zone): string
    {
        $acct = config('services.bunny.account_key');
        if (!$acct) {
            return '';
        }
        $out = [];
        exec($this->esc($this->curl) . ' -s --max-time 20 -H ' . $this->esc('AccessKey: ' . $acct)
            . ' ' . $this->esc('https://api.bunny.net/storagezone'), $out);
        $zones = json_decode(implode('', $out), true);
        if (is_array($zones)) {
            foreach ($zones as $z) {
                if (($z['Name'] ?? '') === $zone) {
                    return (string) ($z['Password'] ?? '');
                }
            }
        }

        return '';
    }

    private function esc(string $s): string
    {
        return '"' . str_replace('"', '\\"', $s) . '"';
    }
}
