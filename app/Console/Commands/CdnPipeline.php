<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CdnPipeline extends Command
{
    protected $signature = 'cdn:pipeline';

    protected $description = 'Run the full move to Bunny: local upload+swap, URL swap, warm crawl. Every stage is resumable; markers in storage/app/cdn make re-runs skip finished stages.';

    public function handle(): int
    {
        @mkdir(config('cdn.state_dir', storage_path('app/cdn')), 0777, true);

        // sanity: DB reachable (keepalive restarts MySQL and relaunches us if not)
        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            $this->error('database unreachable: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! file_exists(config('cdn.state_dir', storage_path('app/cdn')) . '/'.'stage1-local.done')) {
            $this->info('=== stage 1: upload local images + swap to sm-media ===');
            if ($this->call('products:upload-local-images', ['--pool' => 24, '--swap' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }
            file_put_contents(config('cdn.state_dir', storage_path('app/cdn')) . '/'.'stage1-local.done', now()->toDateTimeString());
        } else {
            $this->info('stage 1 already done, skipping');
        }

        if (! file_exists(config('cdn.state_dir', storage_path('app/cdn')) . '/'.'stage2-swap.done')) {
            $this->info('=== stage 2: swap external image URLs to pull zones ===');
            if ($this->call('products:swap-to-cdn') !== self::SUCCESS) {
                return self::FAILURE;
            }
            file_put_contents(config('cdn.state_dir', storage_path('app/cdn')) . '/'.'stage2-swap.done', now()->toDateTimeString());
        } else {
            $this->info('stage 2 already done, skipping');
        }

        if (! file_exists(config('cdn.state_dir', storage_path('app/cdn')) . '/'.'warm.done')) {
            // Fronts first: listing images are what visitors and the business
            // depend on. Galleries warm in a later scope=all pass (organic
            // traffic also fills them via Perma-Cache in the meantime).
            $this->info('=== stage 3: warm crawl, front images (Perma-Cache fill) ===');

            return $this->call('products:warm-cdn', ['--scope' => 'fronts']);
        }
        $this->info('warm already done — pipeline complete');

        return self::SUCCESS;
    }
}
