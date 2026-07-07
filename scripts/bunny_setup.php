<?php
/**
 * Bunny setup helper — reads BUNNY_ACCOUNT_KEY from .env, talks to the Bunny API.
 * Usage:
 *   php scripts/bunny_setup.php recon                 # list storage zones + pull zones (read-only)
 *   php scripts/bunny_setup.php create <name> <origin> [referer]   # create a perma-cache pull zone
 * Never prints the secret keys.
 */

$env = [];
foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
        $env[$m[1]] = trim($m[2], "\"'");
    }
}
$ACCOUNT = $env['BUNNY_ACCOUNT_KEY'] ?? '';
$STORAGE_ZONE = $env['BUNNY_STORAGE_ZONE'] ?? '';
if ($ACCOUNT === '') {
    fwrite(STDERR, "NO BUNNY_ACCOUNT_KEY in .env\n");
    exit(2);
}

function api(string $method, string $path, ?array $body, string $key): array
{
    $ch = curl_init("https://api.bunny.net{$path}");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['AccessKey: ' . $key, 'Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body !== null ? json_encode($body) : null,
        CURLOPT_TIMEOUT => 40,
    ]);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode((string) $out, true)];
}

$mode = $argv[1] ?? 'recon';

if ($mode === 'recon') {
    [$sc, $sz] = api('GET', '/storagezone', null, $ACCOUNT);
    echo "storage zones (HTTP $sc):\n";
    foreach (($sz['Items'] ?? $sz ?? []) as $z) {
        $mark = ($z['Name'] ?? '') === $STORAGE_ZONE ? '  <== target' : '';
        echo "  id={$z['Id']}  name={$z['Name']}  region={$z['Region']}  files=" . ($z['FilesStored'] ?? '?') . "$mark\n";
    }
    [$pc, $pz] = api('GET', '/pullzone?perPage=1000', null, $ACCOUNT);
    echo "\npull zones (HTTP $pc):\n";
    foreach (($pz['Items'] ?? $pz ?? []) as $z) {
        $perma = $z['PermaCacheStorageZoneId'] ?? 0;
        echo "  id={$z['Id']}  name={$z['Name']}  origin={$z['OriginUrl']}  permaCacheSZ={$perma}  hostnames=" .
            implode(',', array_map(fn ($h) => $h['Value'] ?? '', $z['Hostnames'] ?? [])) . "\n";
    }
    exit(0);
}

if ($mode === 'create') {
    $name = $argv[2] ?? '';
    $origin = $argv[3] ?? '';
    $referer = $argv[4] ?? '';
    if ($name === '' || $origin === '') {
        fwrite(STDERR, "usage: create <name> <origin> [referer]\n");
        exit(2);
    }
    // find storage zone id for perma-cache
    [$sc, $sz] = api('GET', '/storagezone', null, $ACCOUNT);
    $szId = null;
    foreach (($sz['Items'] ?? $sz ?? []) as $z) {
        if (($z['Name'] ?? '') === $STORAGE_ZONE) {
            $szId = $z['Id'];
        }
    }
    if (!$szId) {
        fwrite(STDERR, "storage zone {$STORAGE_ZONE} not found\n");
        exit(3);
    }
    // create pull zone with perma-cache into the storage zone
    [$code, $pz] = api('POST', '/pullzone', [
        'Name' => $name,
        'OriginUrl' => $origin,
        'PermaCacheStorageZoneId' => $szId,
        'CacheErrorResponses' => false,
        'EnableSmartCache' => true,
    ], $ACCOUNT);
    if ($code >= 300) {
        fwrite(STDERR, "create failed HTTP $code: " . json_encode($pz) . "\n");
        exit(4);
    }
    $pzId = $pz['Id'];
    $host = $pz['Hostnames'][0]['Value'] ?? "{$name}.b-cdn.net";
    echo "created pull zone id={$pzId} host={$host} permaCacheSZ={$szId}\n";
    // add origin Referer header via an edge rule (Set Request Header -> origin)
    if ($referer !== '') {
        [$ec, $er] = api('POST', "/pullzone/{$pzId}/edgerules/addOrUpdate", [
            'ActionType' => 20, // SetRequestHeader
            'ActionParameter1' => 'Referer',
            'ActionParameter2' => $referer,
            'Enabled' => true,
            'Description' => 'Send Referer to origin (hotlink bypass)',
            'TriggerMatchingType' => 0,
            'Triggers' => [[
                'Type' => 1, // Url
                'PatternMatchingType' => 0,
                'PatternMatches' => ['*'],
                'Parameter1' => '',
            ]],
        ], $ACCOUNT);
        echo "  referer edge-rule HTTP $ec\n";
    }
    echo "PZDIR=pullzone__{$name}__{$pzId}\n";
    exit(0);
}
