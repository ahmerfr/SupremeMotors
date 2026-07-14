<?php
/**
 * Delete the ~1.66 TB of DEAD perma-cache files from the failed PUT approach.
 * Those used the pull-zone Id in the path (wrong); Bunny serves the CacheVersion
 * path, so the Id-path folders serve nothing and just cost storage.
 *
 * DELETES (safe — not served, not the durable copies):
 *   __bcdn_perma_cache__/pullzone__sm-goonet__6120836/
 *   __bcdn_perma_cache__/pullzone__sm-autowini__6120837/
 * KEEPS the durable GET-warm folders (…__56383042 / …__56383043).
 *
 * Run:  php scripts/cleanup_dead_permacache.php          (dry run — lists only)
 *       php scripts/cleanup_dead_permacache.php --delete  (actually deletes)
 */
$env = [];
foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES) as $l) {
    if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $l, $m)) $env[$m[1]] = trim($m[2], "\"'");
}
$ak = $env['BUNNY_ACCOUNT_KEY'] ?? '';
$c = curl_init('https://api.bunny.net/storagezone/1630956');
curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_HTTPHEADER => ["AccessKey: $ak"]]);
$z = json_decode(curl_exec($c), true); curl_close($c);
$pw = $z['Password'] ?? '';

$do = in_array('--delete', $argv, true);
$dead = [
    '__bcdn_perma_cache__/pullzone__sm-goonet__6120836/',
    '__bcdn_perma_cache__/pullzone__sm-autowini__6120837/',
];
foreach ($dead as $d) {
    $url = "https://storage.bunnycdn.com/suprememotors-media/$d";
    if (!$do) {
        // list to confirm it's the dead folder
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_HTTPHEADER => ["AccessKey: $pw", 'Accept: application/json']]);
        $items = json_decode(curl_exec($c), true); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
        echo "DRY RUN  $d  (HTTP $code)  top-level entries: " . (is_array($items) ? count($items) : 0) . "\n";
    } else {
        // recursive delete of the folder
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_RETURNTRANSFER => 1, CURLOPT_HTTPHEADER => ["AccessKey: $pw"]]);
        curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
        echo "DELETE  $d  -> HTTP $code " . ($code === 200 ? '(deleted)' : '(check)') . "\n";
    }
}
if (!$do) echo "\nDry run only. Re-run with --delete to remove them.\n";
