<?php

namespace App\Console\Commands;

use App\Models\Categories;
use Illuminate\Console\Command;

class CategoriesBuildHierarchy extends Command
{
    protected $signature = 'categories:build-hierarchy';

    protected $description = 'Create the 7 top-level categories and file every other category under its parent';

    private const PARENTS = ['Cars', 'Trucks', 'Electric Vehicles', 'Tractors', 'Buses', 'Heavy Machinery', 'Equipment'];

    private const MAPPING = [
        'Trucks' => [
            'Semi Trailers', 'TractorTruck', 'DumpTruck', 'GarbageTruck', 'TankTruck', 'Mixertruck',
            'Municipal Trucks', 'Refrigerated Truck', 'Commercial Vehicles', 'Trailers', 'Tank Transports',
        ],
        'Electric Vehicles' => ['Electric Cars', 'Bicycle'],
        'Tractors' => ['Wheel Tractors', 'Mini Tractors', 'Moto Tractors', 'Crawler Tractors'],
        'Heavy Machinery' => [
            'Excavators', 'Construction Loaders', 'Cranes', 'EarthMoving Equipments', 'Concrete Equipments',
            'Construction Rollers', 'Road Construction Equipment', 'Drilling Machinery', 'Aerial Platform',
            'Telehandler', 'Rotating TeleHandler', 'Surface Finishing', 'Building Equipments',
        ],
        'Equipment' => [
            'Diesel Forklift', 'Electric Forklift', 'Gas Forklift', 'Petrol Forklift', 'Petrol Gas Forklift',
            'High Capacity Forklift', 'Rough Terrain Forklift', 'Articulated Forklifts', 'Three Wheel Forklift',
            'Truck Mounted Forklift', 'Fork Lifts', 'Reach Trucks', 'Order Pickers', 'Side Loader',
            'Container Handlers', 'Modular Container', 'Containers', 'Airport Equipments', 'Railway Equipment',
        ],
    ];

    public function handle(): int
    {
        $parentIds = [];
        foreach (self::PARENTS as $name) {
            $parent = Categories::firstOrCreate(
                ['cat_title' => $name, 'type' => 'category'],
                ['description' => '--']
            );
            if ($parent->parent_id !== null) {
                $parent->update(['parent_id' => null]);
            }
            $parentIds[$name] = $parent->id;
        }

        foreach (self::MAPPING as $parentName => $childTitles) {
            $updated = Categories::where('type', 'category')
                ->whereIn('cat_title', $childTitles)
                ->whereNotIn('id', $parentIds)
                ->update(['parent_id' => $parentIds[$parentName]]);
            $this->info("$parentName: $updated children");
        }

        $orphans = Categories::where('type', 'category')
            ->whereNull('parent_id')
            ->whereNotIn('id', $parentIds)
            ->pluck('cat_title');
        if ($orphans->isNotEmpty()) {
            $this->warn('Unmapped top-level categories: ' . $orphans->implode(', '));
        }

        return self::SUCCESS;
    }
}
