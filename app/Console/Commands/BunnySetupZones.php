<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BunnySetupZones extends Command
{
    protected $signature = 'bunny:setup-zones';

    protected $description = 'Create the per-source Bunny pull zones with Perma-Cache into our storage zone (idempotent)';

    /** pull zone name => origin */
    private const ZONES = [
        'sm-linemedia' => 'https://img.linemedia.com',
        'sm-madeinchina' => 'https://image.made-in-china.com',
        'sm-tcv' => 'https://www.tc-v.com',
        'sm-autotrader' => 'https://img.autotrader.co.za',
    ];

    public function handle(): int
    {
        $accountKey = config('services.bunny.account_key');
        $storageZoneName = config('services.bunny.storage_zone');
        if (!$accountKey || !$storageZoneName) {
            $this->error('Set BUNNY_ACCOUNT_KEY and BUNNY_STORAGE_ZONE in .env first.');
            return self::FAILURE;
        }

        $api = fn () => Http::withHeaders(['AccessKey' => $accountKey, 'Accept' => 'application/json'])->timeout(30);

        // find our storage zone id for perma-cache
        $storageZones = $api()->get('https://api.bunny.net/storagezone', ['page' => 1, 'perPage' => 1000]);
        if (!$storageZones->successful()) {
            $this->error("Could not list storage zones (HTTP {$storageZones->status()}) — is the account API key correct?");
            return self::FAILURE;
        }
        // some Bunny endpoints return a plain array, others a paged {Items:[]}
        $zoneList = collect($storageZones->json('Items') ?? $storageZones->json());
        $this->info('storage zones on account: ' . ($zoneList->pluck('Name')->implode(', ') ?: '(none)'));
        $storage = $zoneList->firstWhere('Name', $storageZoneName);
        if (!$storage) {
            $create = $api()->post('https://api.bunny.net/storagezone', [
                'Name' => $storageZoneName,
                'Region' => 'DE',
                'ZoneTier' => 0,
            ]);
            if (!$create->successful()) {
                $this->error("Could not create storage zone '{$storageZoneName}' (HTTP {$create->status()}): " . $create->body());
                return self::FAILURE;
            }
            $storage = $create->json();
            $this->info("created storage zone {$storageZoneName} (id {$storage['Id']}) — copy its Password from the dashboard into BUNNY_STORAGE_KEY when direct uploads are needed");
        }
        $this->info("storage zone: {$storageZoneName} (id {$storage['Id']})");

        $existing = collect($api()->get('https://api.bunny.net/pullzone')->json())->keyBy('Name');

        foreach (self::ZONES as $name => $origin) {
            $zone = $existing->get($name);

            if (!$zone) {
                $create = $api()->post('https://api.bunny.net/pullzone', [
                    'Name' => $name,
                    'OriginUrl' => $origin,
                    'Type' => 1, // volume tier: cheapest for images
                ]);
                if (!$create->successful()) {
                    $this->error("create {$name} failed (HTTP {$create->status()}): " . $create->body());
                    return self::FAILURE;
                }
                $zone = $create->json();
                $this->info("created pull zone {$name} (id {$zone['Id']})");
            } else {
                $this->info("pull zone {$name} already exists (id {$zone['Id']})");
            }

            // enable perma-cache into our storage zone
            if (($zone['PermaCacheStorageZoneId'] ?? 0) !== $storage['Id']) {
                $update = $api()->post("https://api.bunny.net/pullzone/{$zone['Id']}", [
                    'PermaCacheStorageZoneId' => $storage['Id'],
                ]);
                if (!$update->successful()) {
                    $this->error("perma-cache on {$name} failed (HTTP {$update->status()}): " . $update->body());
                    return self::FAILURE;
                }
                $this->info("perma-cache enabled on {$name}");
            }

            $host = collect($zone['Hostnames'] ?? [])->pluck('Value')->first(fn ($h) => str_ends_with($h, '.b-cdn.net'))
                ?? "{$name}.b-cdn.net";
            $this->info("  -> {$origin}  =>  https://{$host}");
        }

        return self::SUCCESS;
    }
}
