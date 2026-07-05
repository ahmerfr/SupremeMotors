<?php

namespace App\Services;

/**
 * HTTP transport that shells out to the Windows-bundled curl.exe.
 *
 * WHY THIS EXISTS: autotrader.co.uk sits behind Cloudflare Bot Management,
 * which fingerprints the TLS ClientHello (JA3). PHP's own libcurl is built
 * against OpenSSL and its fingerprint is flagged -> every Guzzle/Http request
 * gets a 403, even the plain homepage GET. The Windows system curl.exe is built
 * against Schannel (the OS TLS stack); its fingerprint passes cleanly and every
 * request returns 200. So the ENTIRE UK transport goes through curl.exe.
 *
 * Concurrency is done by a single `curl.exe --parallel` process per wave: it
 * opens up to N transfers at once inside one process (far cheaper than spawning
 * N processes) and we map each result back by curl's %{urlnum} index. Cookies
 * (the __cf_bm Cloudflare token) live in a shared jar FILE so the homepage
 * handshake and the gateway calls reuse the same session, exactly like a
 * browser — no manual Set-Cookie parsing.
 */
class SchannelCurl
{
    /** Schannel-built curl that ships with Windows 10/11 (passes Cloudflare). */
    private string $bin;

    private string $workDir;

    /** @param string|null $bin override for tests; defaults to the OS curl.exe */
    public function __construct(?string $bin = null, ?string $workDir = null)
    {
        $this->bin = $bin ?? 'C:\\Windows\\System32\\curl.exe';
        $this->workDir = $workDir ?? sys_get_temp_dir();
        if (!is_dir($this->workDir)) {
            @mkdir($this->workDir, 0777, true);
        }
    }

    /**
     * A single request. Returns [status, body]. Uses -c/-b so any Set-Cookie
     * (e.g. a freshly minted __cf_bm) is written back into the jar for reuse.
     *
     * @param  array<string,string>  $headers
     * @return array{0:int,1:string}
     */
    public function request(string $method, string $url, array $headers, ?string $body, string $jar, int $timeout = 40): array
    {
        $out = $this->tmp('out');
        $args = [
            '-s', '--compressed', '--max-time', (string) $timeout,
            '-o', $out, '-w', '%{http_code}',
            '-c', $jar, '-b', $jar,
            '-X', $method,
        ];
        foreach ($headers as $k => $v) {
            $args[] = '-H';
            $args[] = "$k: $v";
        }
        $bodyFile = null;
        if ($body !== null) {
            $bodyFile = $this->tmp('body');
            file_put_contents($bodyFile, $body);
            $args[] = '--data-binary';
            $args[] = '@' . $bodyFile;
        }
        $args[] = $url;

        $status = (int) trim($this->run($args));
        $payload = is_file($out) ? (string) file_get_contents($out) : '';
        @unlink($out);
        if ($bodyFile) {
            @unlink($bodyFile);
        }

        return [$status, $payload];
    }

    /**
     * Run many requests concurrently through a pool of curl.exe processes.
     * Each request is [method,url,headers,body?]; results come back keyed by the
     * SAME array keys with [status, body]. Up to $poolMax processes run at once;
     * as each finishes a new one is launched (rolling window).
     *
     * Each process is byte-for-byte the same invocation as request() — the one
     * we proved returns 200 real data — so there are no curl-config quirks. The
     * jar is read-only here (-b, no -c): __cf_bm is minted by the homepage
     * handshake before this runs, and letting N processes rewrite one jar file
     * concurrently would corrupt it. Cloudflare doesn't rotate the token mid-wave.
     *
     * @param  array<int|string,array{method:string,url:string,headers:array<string,string>,body?:string|null}>  $requests
     * @return array<int|string,array{0:int,1:string}>  key => [status, body]
     */
    public function parallel(array $requests, string $jar, int $poolMax, int $timeout = 40): array
    {
        if ($requests === []) {
            return [];
        }

        $queue = array_keys($requests);
        $running = [];   // key => ['proc'=>res,'pipes'=>[],'out'=>path,'body'=>?path]
        $results = [];
        $poolMax = max(1, $poolMax);

        while ($queue !== [] || $running !== []) {
            while ($queue !== [] && count($running) < $poolMax) {
                $key = array_shift($queue);
                $running[$key] = $this->launch($requests[$key], $jar, $timeout);
            }

            foreach ($running as $key => $h) {
                $st = proc_get_status($h['proc']);
                if ($st['running']) {
                    continue;
                }
                $code = (int) trim(stream_get_contents($h['pipes'][1]));
                fclose($h['pipes'][1]);
                fclose($h['pipes'][2]);
                proc_close($h['proc']);
                $body = is_file($h['out']) ? (string) file_get_contents($h['out']) : '';
                $results[$key] = [$code, $body];
                @unlink($h['out']);
                if ($h['body'] !== null) {
                    @unlink($h['body']);
                }
                unset($running[$key]);
            }

            if ($running !== []) {
                usleep(20000); // 20ms poll — keeps CPU idle while curls run
            }
        }

        return $results;
    }

    /**
     * Start one non-blocking curl.exe process for a request.
     * @param  array{method:string,url:string,headers:array<string,string>,body?:string|null}  $r
     * @return array{proc:resource,pipes:array,out:string,body:?string}
     */
    private function launch(array $r, string $jar, int $timeout): array
    {
        $out = $this->tmp('out');
        $args = [
            '-s', '--compressed', '--max-time', (string) $timeout,
            '-o', $out, '-w', '%{http_code}',
            '-b', $jar,
            '-X', $r['method'] ?? 'GET',
        ];
        foreach (($r['headers'] ?? []) as $k => $v) {
            $args[] = '-H';
            $args[] = "$k: $v";
        }
        $bodyFile = null;
        if (($r['method'] ?? 'GET') === 'POST' && ($r['body'] ?? null) !== null) {
            $bodyFile = $this->tmp('body');
            file_put_contents($bodyFile, $r['body']);
            $args[] = '--data-binary';
            $args[] = '@' . $bodyFile;
        }
        $args[] = $r['url'];

        $cmd = $this->esc($this->bin);
        foreach ($args as $a) {
            $cmd .= ' ' . $this->esc($a);
        }
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        return ['proc' => $proc, 'pipes' => $pipes, 'out' => $out, 'body' => $bodyFile];
    }

    /** run curl.exe with an argv array (blocking); returns stdout */
    private function run(array $args): string
    {
        $cmd = $this->esc($this->bin);
        foreach ($args as $a) {
            $cmd .= ' ' . $this->esc($a);
        }

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return '';
        }
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return $stdout ?: '';
    }

    /** Windows-safe argv escaping for proc_open command strings */
    private function esc(string $s): string
    {
        return '"' . str_replace('"', '\\"', $s) . '"';
    }

    private function tmp(string $prefix): string
    {
        return $this->workDir . DIRECTORY_SEPARATOR . $prefix . '_' . bin2hex(random_bytes(6));
    }
}
