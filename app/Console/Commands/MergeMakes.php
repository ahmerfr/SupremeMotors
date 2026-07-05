<?php

namespace App\Console\Commands;

use App\Models\Categories;
use App\Models\Products;
use App\Services\MakeNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Merge duplicate/variant make categories into their canonical make. For every
 * make whose name the normalizer rewrites (e.g. "Mercedes-Benz" -> "Mercedes
 * Benz"), all its products are reassigned to the canonical make and the now
 * empty variant category is deleted. Idempotent; --dry-run previews.
 */
class MergeMakes extends Command
{
    protected $signature = 'products:merge-makes {--dry-run}';

    protected $description = 'Collapse duplicate make categories (spelling variants) into one canonical make each';

    public function handle(MakeNormalizer $normalizer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $makes = Categories::where('type', 'make')->get(['id', 'cat_title']);
        $merged = 0;
        $moved = 0;

        foreach ($makes as $make) {
            $canonical = $normalizer->canonical($make->cat_title);
            if ($canonical === null || strcasecmp($canonical, $make->cat_title) === 0) {
                continue; // already canonical
            }

            // find (or, outside dry-run, create) the canonical make
            $target = Categories::where('type', 'make')->where('cat_title', $canonical)->first();
            if (!$target) {
                if ($dryRun) {
                    $this->line("  would create '{$canonical}' and merge '{$make->cat_title}' into it");

                    continue;
                }
                $target = Categories::create(['cat_title' => $canonical, 'type' => 'make']);
            }
            if ($target->id === $make->id) {
                continue;
            }

            $count = Products::where('make_id', $make->id)->count();
            $this->line("  '{$make->cat_title}' (#{$make->id}, {$count} cars) -> '{$canonical}' (#{$target->id})");

            if (!$dryRun) {
                DB::transaction(function () use ($make, $target) {
                    Products::where('make_id', $make->id)->update(['make_id' => $target->id]);
                    $make->delete();
                });
            }
            $merged++;
            $moved += $count;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "merged {$merged} variant makes, reassigned {$moved} cars");

        return self::SUCCESS;
    }
}
