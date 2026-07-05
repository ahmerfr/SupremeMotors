<?php

namespace Tests\Unit;

use App\Services\CategoryRouter;
use PHPUnit\Framework\TestCase;

class CategoryRouterTest extends TestCase
{
    private CategoryRouter $r;

    protected function setUp(): void
    {
        $this->r = new CategoryRouter;
    }

    /** @dataProvider cases */
    public function test_routes(string $expected, ?string $body, ?string $title): void
    {
        $this->assertSame($expected, $this->r->resolve($body, $title));
    }

    public static function cases(): array
    {
        return [
            // passenger shapes -> Cars
            'suv' => ['Cars', 'SUV', '2015 Toyota Land Cruiser'],
            'hatchback' => ['Cars', 'Hatchback', '2020 VW Polo'],
            'sedan' => ['Cars', 'Sedan', '2019 Honda Civic'],
            'mpv' => ['Cars', 'MPV', '2018 Toyota Avanza'],
            // the classic false positive: Cabriolet must NOT match "cab"
            'cabriolet-stays-cars' => ['Cars', 'Cabriolet', '2016 BMW 4 Series'],
            'coupe' => ['Cars', 'Coupé', '2017 Audi A5'],

            // bakkies / pickups -> Trucks
            'double-cab' => ['Trucks', 'Double cab', '2021 Toyota Hilux'],
            'single-cab' => ['Trucks', 'Single cab', '2019 Isuzu D-Max'],
            'supercab' => ['Trucks', 'Supercab', '2018 Ford Ranger'],
            'hilux-by-title' => ['Trucks', null, '2017 Toyota Hilux'],       // perfect-motors: no body
            'canter-truck' => ['Trucks', null, '1998 Mitsubishi Canter'],

            // vans -> Commercial Vehicles
            'panel-van' => ['Commercial Vehicles', 'Panel van', '2015 VW Caddy'],
            'lcv' => ['Commercial Vehicles', 'LCV', 'some van'],
            'hiace-van' => ['Commercial Vehicles', null, '2007 Toyota Hiace'],
            'caravan-is-van' => ['Commercial Vehicles', null, '2005 Nissan Caravan'],

            // buses -> Buses
            'minibus' => ['Buses', 'Minibus', '2016 Toyota Quantum'],
            'crew-bus-not-cab' => ['Buses', 'Crew bus', '2014 Mercedes Sprinter'],
            'coaster-bus' => ['Buses', null, '2010 Toyota Coaster'],

            // body_style is authoritative — a known car body wins over any
            // van/MPV-ish model name in the title (the 754-row bug)
            'suv-title-noise-stays-cars' => ['Cars', 'SUV', 'Mitsubishi Delica SUV'],
            'mpv-noah-stays-cars' => ['Cars', 'MPV', '2018 Toyota Noah'],
            'sedan-stays-cars' => ['Cars', 'Sedan', '2016 Toyota Voxy something'],

            // default
            'unknown-defaults-cars' => ['Cars', null, '2012 Toyota Aqua'],
            'null-defaults-cars' => ['Cars', null, null],
        ];
    }
}
