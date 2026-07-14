<?php
/**
 * Purge a product's Bunny CDN image URLs so stale-cached 403s re-pull fresh.
 * Per-URL purge only — does NOT bump CacheVersion or touch perma-cache.
 *
 * Run:  php scripts/purge_product.php 1509849
 */
$id = $argv[1] ?? '';
if (!$id) { fwrite(STDERR, "usage: php scripts/purge_product.php <product-id>\n"); exit(1); }

$env = [];
foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES) as $l) {
    if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $l, $m)) $env[$m[1]] = trim($m[2], "\"'");
}
$ak = $env['BUNNY_ACCOUNT_KEY'] ?? '';

// fetch the product page, pull out its Bunny image URLs
$page = file_get_contents("https://suprememotors.ltd/inventory/product-detail/$id");
$page = html_entity_decode(str_replace('\/', '/', $page));
preg_match_all('#https://sm-(?:autowini|goonet)\.b-cdn\.net/[^"\\\\ ]+?\.(?:jpg|jpeg|png|webp)#i', $page, $m);
$urls = array_values(array_unique($m[0]));
echo "found " . count($urls) . " image URLs for product $id\n";

$ok = 0;
foreach ($urls as $u) {
    $c = curl_init('https://api.bunny.net/purge?async=false&url=' . urlencode($u));
    curl_setopt_array($c, [CURLOPT_POST => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_HTTPHEADER => ["AccessKey: $ak"]]);
    curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    if ($code === 200) $ok++;
}
echo "purged $ok/" . count($urls) . " (next request re-pulls fresh -> serves the image)\n";
