<?php
/**
 * Durability check: does GET-warming actually persist images into Bunny perma-cache
 * storage? Reads BUNNY_ACCOUNT_KEY from .env, fetches the storage-zone password from
 * the account API, then lists the perma-cache folders for sm-autowini / sm-goonet.
 *
 * Run:  php scripts/check_permacache.php
 */
$env = [];
foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES) as $l) {
    if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $l, $m)) $env[$m[1]] = trim($m[2], "\"'");
}
$ak = $env['BUNNY_ACCOUNT_KEY'] ?? '';
if (!$ak) { fwrite(STDERR, "no BUNNY_ACCOUNT_KEY in .env\n"); exit(1); }

$c = curl_init('https://api.bunny.net/storagezone/1630956');
curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_HTTPHEADER => ["AccessKey: $ak"]]);
$z = json_decode(curl_exec($c), true); curl_close($c);
$pw = $z['Password'] ?? $z['ReadOnlyPassword'] ?? '';
echo 'Storage zone FilesStored (API): ' . number_format($z['FilesStored'] ?? 0) .
     '   ' . round(($z['StorageUsed'] ?? 0) / 1e9, 1) . " GB\n";

function ls($pw, $dir) {
    $c = curl_init("https://storage.bunnycdn.com/suprememotors-media/$dir");
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_HTTPHEADER => ["AccessKey: $pw", 'Accept: application/json']]);
    $r = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    return [$code, json_decode($r, true)];
}
foreach ([
    '__bcdn_perma_cache__/pullzone__sm-autowini__56383043/',
    '__bcdn_perma_cache__/pullzone__sm-goonet__56383042/',
] as $d) {
    [$code, $items] = ls($pw, $d);
    echo "\n$d  (HTTP $code)\n";
    if (is_array($items)) {
        echo '  entries: ' . count($items) . "\n";
        foreach (array_slice($items, 0, 6) as $it) {
            echo '   - ' . ($it['ObjectName'] ?? '?') . (($it['IsDirectory'] ?? false) ? '/' : '') .
                 '  ' . number_format($it['Length'] ?? 0) . "b\n";
        }
    } else {
        echo "  (empty or not created yet)\n";
    }
}
