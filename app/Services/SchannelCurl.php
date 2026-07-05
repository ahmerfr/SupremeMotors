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
 * Concurrency is done by a single `curl.exe -Z --parallel` process per wave: it
 * opens up to N transfers at once inside ONE process (far cheaper than spawning
 * N processes, and it reuses TCP/TLS connections across transfers) and we map
 * each result back by curl's %{urlnum} index. The whole wave is described in a
 * curl CONFIG FILE fed with -K, because 460k URLs will never fit on a command
 * line. Cookies (the __cf_bm Cloudflare token) live in a shared jar FILE so the
 * homepage handshake and the gateway calls reuse the same session, exactly like
 * a browser — no manual Set-Cookie parsing.
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
     * Run many requests concurrently through ONE `curl.exe -Z --parallel` process.
     * Each request is [method,url,headers,body?]; results come back keyed by the
     * SAME array keys with [status, body]. Up to $poolMax transfers run at once
     * inside a single process, reusing TCP/TLS connections across transfers.
     *
     * The wave is described in a curl CONFIG FILE (-K), one --next-separated block
     * per transfer, because 460k URLs won't fit on a command line. Each transfer
     * writes its body to its own output tempfile and its "%{urlnum} %{http_code}
     * %{exitcode}" line to stdout; we map those back by urlnum -> ordered key.
     *
     * The jar is READ-ONLY here (cookie=<jar>, i.e. -b, NEVER -c): __cf_bm is
     * minted by the homepage handshake before this runs, and letting N concurrent
     * transfers rewrite one jar file would corrupt it. Cloudflare doesn't rotate
     * the token mid-wave. A transfer with no stdout status line (curl crashed or
     * timed out) comes back as [0, ''] so the caller's retry logic handles it.
     *
     * @param  array<int|string,array{method:string,url:string,headers:array<string,string>,body?:string|null}>  $requests
     * @return array<int|string,array{0:int,1:string}>  key => [status, body]
     */
    public function parallel(array $requests, string $jar, int $poolMax, int $timeout = 40): array
    {
        if ($requests === []) {
            return [];
        }

        $poolMax = max(1, $poolMax);
        $keys = array_keys($requests);   // urlnum (0-based, config order) => original key

        // Per-transfer output files, indexed by urlnum. Also track POST body files.
        $outFiles = [];
        $bodyFiles = [];

        // ---- Build the curl config file for this wave --------------------------
        $lines = [
            'parallel',
            'parallel-max = ' . $poolMax,
            'parallel-max-host = ' . $poolMax, // CRITICAL: default 5 caps concurrency to one host
            'compressed',
            'silent',
            'max-time = ' . $timeout,
            'cookie = ' . $this->cfgQuote($jar), // read-only jar (-b), NO -c
        ];

        foreach ($keys as $urlnum => $key) {
            $r = $requests[$key];
            $method = $r['method'] ?? 'GET';

            $out = $this->tmp('out');
            $outFiles[$urlnum] = $out;

            $lines[] = '--next';
            $lines[] = 'url = ' . $this->cfgQuote($r['url']);
            $lines[] = 'output = ' . $this->cfgQuote($out);
            $lines[] = 'write-out = ' . $this->cfgQuote($urlnum . ' %{http_code} %{exitcode}\\n');
            $lines[] = 'request = ' . $this->cfgQuote($method);

            foreach (($r['headers'] ?? []) as $hk => $hv) {
                $lines[] = 'header = ' . $this->cfgQuote($hk . ': ' . $hv);
            }

            if ($method === 'POST' && ($r['body'] ?? null) !== null) {
                $bodyFile = $this->tmp('body');
                file_put_contents($bodyFile, $r['body']);
                $bodyFiles[$urlnum] = $bodyFile;
                // @file makes curl read the raw bytes; the leading @ is a curl directive,
                // the path itself is a plain value so it does NOT go through cfgQuote.
                $lines[] = 'data-binary = ' . $this->cfgQuote('@' . $bodyFile);
            }
        }

        $cfgFile = $this->tmp('cfg');
        file_put_contents($cfgFile, implode("\n", $lines) . "\n");

        // ---- Run the single -Z process -----------------------------------------
        // -K reads the whole config; every transfer carries max-time=$timeout, so
        // a stalled host self-caps and the process can't hang the wave forever.
        $stdout = $this->run(['-K', $cfgFile]);

        // ---- Parse "urlnum status exitcode" lines from stdout -------------------
        $status = []; // urlnum => http_code
        foreach (explode("\n", $stdout) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 3 || !ctype_digit($parts[0])) {
                continue;
            }
            $status[(int) $parts[0]] = (int) $parts[1];
        }

        // ---- Assemble results, one per input key --------------------------------
        $results = [];
        foreach ($keys as $urlnum => $key) {
            if (!isset($status[$urlnum])) {
                // No status line -> curl crashed/timed out for this transfer.
                $results[$key] = [0, ''];
            } else {
                $body = is_file($outFiles[$urlnum])
                    ? (string) file_get_contents($outFiles[$urlnum])
                    : '';
                $results[$key] = [$status[$urlnum], $body];
            }
        }

        // ---- Clean up every temp file this wave created -------------------------
        @unlink($cfgFile);
        foreach ($outFiles as $f) {
            @unlink($f);
        }
        foreach ($bodyFiles as $f) {
            @unlink($f);
        }

        return $results;
    }

    /**
     * Quote a value for a curl config file (-K). Inside a double-quoted config
     * value, backslash is an escape char, so a literal backslash must be doubled
     * and a literal double-quote must be backslash-escaped. Windows paths are full
     * of backslashes — a mis-escape silently breaks the transfer. We deliberately
     * emit the escape sequence \n literally (callers pass '\\n' as two chars) so
     * curl expands it to a newline in write-out.
     */
    private function cfgQuote(string $s): string
    {
        // Preserve an already-literal "\n" (backslash + n) that callers embed for
        // write-out newlines: temporarily shield it before doubling backslashes.
        $placeholder = "\x00NL\x00";
        $s = str_replace('\\n', $placeholder, $s);
        $s = str_replace('\\', '\\\\', $s);   // literal backslash -> \\
        $s = str_replace('"', '\\"', $s);     // literal quote -> \"
        $s = str_replace($placeholder, '\\n', $s);

        return '"' . $s . '"';
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
