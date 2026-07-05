<?php

namespace App\Console\Commands;

use App\Models\Categories;
use App\Models\Products;
use App\Services\CategoryRouter;
use Illuminate\Console\Command;

/**
 * Backfill category_id for already-scraped products using the CategoryRouter
 * (body_style + title). Scoped to a --website so it only touches the sources we
 * mean to re-route; safe to re-run (only writes rows whose category changed).
 */
class RouteCategories extends Command
{
    protected $signature = 'products:route-categories {--website= : Only re-route this source (required)} {--dry-run}';

    protected $description = 'Re-route existing products into the correct category from body_style/title';

    public function handle(CategoryRouter $router): int
    {
        $website = $this->option('website');
        if (!$website) {
            $this->error('--website is required (e.g. --website=autotraderza)');

            return self::FAILURE;
        }
        $dryRun = (bool) $this->option('dry-run');

        // resolve category name -> id once
        $ids = Categories::where('type', 'category')->pluck('id', 'cat_title');
        $carsId = $ids['Cars'] ?? null;

        $moved = [];
        $changed = 0;
        $scanned = 0;

        Products::where('website', $website)
            ->select('id', 'body_style', 'title', 'category_id')
            ->orderBy('id')
            ->chunkById(2000, function ($rows) use ($router, $ids, $carsId, $dryRun, &$moved, &$changed, &$scanned) {
                $updates = [];
                foreach ($rows as $p) {
                    $scanned++;
                    $name = $router->resolve($p->body_style, $p->title);
                    $target = $ids[$name] ?? $carsId;
                    if ($target && $p->category_id !== $target) {
                        $updates[$target][] = $p->id;
                        $moved[$name] = ($moved[$name] ?? 0) + 1;
                        $changed++;
                    }
                }
                if (!$dryRun) {
                    foreach ($updates as $catId => $pids) {
                        // one UPDATE per target category per chunk
                        Products::whereIn('id', $pids)->update(['category_id' => $catId]);
                    }
                }
            });

        $this->info(($dryRun ? '[dry-run] ' : '') . "scanned {$scanned}, re-routed {$changed}:");
        foreach ($moved as $name => $n) {
            $this->line("  -> {$name}: {$n}");
        }

        return self::SUCCESS;
    }
}
