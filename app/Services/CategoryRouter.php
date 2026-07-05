<?php

namespace App\Services;

/**
 * Routes a scraped vehicle into one of the site's real categories.
 *
 * body_style is AUTHORITATIVE when present (AutoTrader supplies it) — a car
 * whose body is SUV/Sedan/MPV/Hatchback is a car no matter what its model name
 * contains, so title keywords must never override it. Only when there is no
 * body_style (perfect-motors) do we classify from the title/model.
 *
 * Business mapping (confirmed):
 *   bakkies / pickups (double/single cab)  -> Trucks
 *   vans (panel van / LCV)                 -> Commercial Vehicles
 *   minibuses / buses                      -> Buses
 *   passenger shapes + anything unknown    -> Cars
 */
class CategoryRouter
{
    public const CARS = 'Cars';
    public const TRUCKS = 'Trucks';
    public const COMMERCIAL = 'Commercial Vehicles';
    public const BUSES = 'Buses';

    /** body_style substrings (after normalisation) — the reliable signal */
    private const BODY_TRUCK = ['double cab', 'single cab', 'extended cab', 'super cab', 'supercab', 'king cab', 'crew cab', 'cab chassis'];
    private const BODY_VAN = ['panel van', 'lcv'];

    /** title/model keywords — only used when there is NO body_style */
    private const TITLE_BUS = ['coaster', 'civilian', ' rosa', 'minibus', 'coach bus', ' bus '];
    private const TITLE_TRUCK = [
        'double cab', 'single cab', 'bakkie', 'pickup', 'pick up',
        'hilux', 'd max', 'dmax', 'bt 50', 'navara', 'ranger', 'amarok', 'gladiator', 'frontier',
        'dyna', 'dutro', 'canter', 'elf', 'titan', 'condor', 'forward', 'atego', 'actros',
    ];
    private const TITLE_VAN = [
        'panel van', 'lcv', 'hiace', 'townace', 'town ace', 'liteace', 'lite ace', 'caravan',
        'vanette', 'urvan', 'nv200', 'nv350', 'nv100', 'bongo', 'porter', 'hijet',
    ];

    public function resolve(?string $bodyStyle, ?string $title = null): string
    {
        if ($bodyStyle !== null && trim($bodyStyle) !== '') {
            $body = $this->norm($bodyStyle);
            // authoritative: classify from the body style alone
            if (str_contains($body, 'bus')) {          // minibus, crew bus
                return self::BUSES;
            }
            if ($this->hasAny($body, self::BODY_TRUCK)) {
                return self::TRUCKS;
            }
            if ($this->hasAny($body, self::BODY_VAN)) {
                return self::COMMERCIAL;
            }

            return self::CARS; // SUV, Hatchback, Sedan, MPV, Coupe, Cabriolet, ...
        }

        // no body style (perfect-motors): fall back to title/model keywords
        $t = $this->norm($title);
        if ($this->hasAny($t, self::TITLE_BUS)) {
            return self::BUSES;
        }
        if ($this->hasAny($t, self::TITLE_TRUCK)) {
            return self::TRUCKS;
        }
        if ($this->hasAny($t, self::TITLE_VAN)) {
            return self::COMMERCIAL;
        }

        return self::CARS;
    }

    /** lowercase, wrap in spaces, collapse separators so " double-cab " matches */
    private function norm(?string $s): string
    {
        $s = ' ' . strtolower(trim((string) $s)) . ' ';
        $s = str_replace(['-', '/', '_'], ' ', $s);

        return preg_replace('/\s+/', ' ', $s);
    }

    /** @param string[] $needles */
    private function hasAny(string $hay, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($hay, str_replace('-', ' ', $n))) {
                return true;
            }
        }

        return false;
    }
}
