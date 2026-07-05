<?php

namespace App\Services;

/**
 * Routes a scraped vehicle into one of the site's real categories from its
 * body style (AutoTrader supplies it) and/or its title/model (perfect-motors
 * has no body field, so we lean on model names). Passenger shapes and anything
 * unknown fall through to Cars.
 *
 * Business mapping (confirmed):
 *   bakkies / pickups  -> Trucks
 *   vans (panel/LCV)   -> Commercial Vehicles
 *   minibuses / buses  -> Buses
 *   everything else    -> Cars
 *
 * Returns a category TITLE; the caller resolves it to an id (falling back to
 * Cars if that category somehow doesn't exist).
 */
class CategoryRouter
{
    public const CARS = 'Cars';
    public const TRUCKS = 'Trucks';
    public const COMMERCIAL = 'Commercial Vehicles';
    public const BUSES = 'Buses';

    /**
     * Order matters: buses before trucks ("Crew bus" vs "Crew cab"), and none
     * of the cab patterns use a bare "cab" so "Cabriolet" never trips Trucks.
     */
    private const BUS_KEYWORDS = [
        'minibus', 'crew bus', 'mini bus', 'coaster', 'civilian', ' rosa', 'omnibus', ' bus ',
    ];

    private const TRUCK_KEYWORDS = [
        'double cab', 'single cab', 'extended cab', 'super cab', 'supercab', 'king cab', 'kingcab',
        'crew cab', 'cab chassis', 'bakkie', 'pickup', 'pick-up', 'pick up',
        // pickup / light-truck model names (perfect-motors has no body field)
        'hilux', 'd-max', 'dmax', 'bt-50', 'navara', 'ranger', 'amarok', 'gladiator', 'frontier',
        'dyna', 'dutro', 'canter', 'elf', 'titan', 'condor', 'forward', 'atego', 'actros',
    ];

    private const VAN_KEYWORDS = [
        'panel van', 'lcv', 'hiace', 'townace', 'town ace', 'liteace', 'lite ace', 'caravan',
        'vanette', 'urvan', 'nv200', 'nv350', 'nv100', 'bongo', 'porter', 'hijet', 'every',
        'voxy', 'noah', 'serena', 'stepwgn', 'delica',
    ];

    public function resolve(?string $bodyStyle, ?string $title = null): string
    {
        $hay = ' ' . strtolower(trim(($bodyStyle ?? '') . ' ' . ($title ?? ''))) . ' ';
        // normalise separators so "double-cab" / "double cab" both match
        $hay = str_replace(['-', '/', '_'], ' ', $hay);
        $hay = preg_replace('/\s+/', ' ', $hay);

        if ($this->hasAny($hay, self::BUS_KEYWORDS)) {
            return self::BUSES;
        }
        if ($this->hasAny($hay, self::TRUCK_KEYWORDS)) {
            return self::TRUCKS;
        }
        if ($this->hasAny($hay, self::VAN_KEYWORDS)) {
            return self::COMMERCIAL;
        }

        return self::CARS;
    }

    /** @param string[] $needles */
    private function hasAny(string $hay, array $needles): bool
    {
        foreach ($needles as $n) {
            // the dash-normalised needles must match the same way
            $n = str_replace('-', ' ', $n);
            if (str_contains($hay, $n)) {
                return true;
            }
        }

        return false;
    }
}
