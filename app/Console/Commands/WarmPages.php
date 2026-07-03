<?php

namespace App\Console\Commands;

use App\Models\Products;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class WarmPages extends Command
{
    protected $signature = 'pages:warm';

    protected $description = 'Rebuild the cached homepage/shop payloads so no visitor pays the rebuild cost';

    public function handle(): int
    {
        // Force-refresh by clearing just these keys, then re-running the
        // controllers' cache closures via internal requests.
        Cache::forget('home_page_data');
        Cache::forget('shop_home_data');
        foreach (Products::whereNotNull('body_style')->distinct()->pluck('body_style') as $style) {
            Cache::forget('home_bt_' . md5($style));
        }

        $router = App::make('router');
        $kernel = App::make(\Illuminate\Contracts\Http\Kernel::class);

        $paths = ['/', '/inventory', '/inventory/listing'];
        foreach (Products::whereNotNull('body_style')->distinct()->pluck('body_style') as $style) {
            $paths[] = '/home/body-type-products?style=' . urlencode($style);
        }

        foreach ($paths as $path) {
            $start = microtime(true);
            $response = $kernel->handle(\Illuminate\Http\Request::create($path, 'GET'));
            $ms = (int) ((microtime(true) - $start) * 1000);
            $this->info(str_pad($response->getStatusCode(), 5) . str_pad("{$ms}ms", 9) . $path);
        }

        return self::SUCCESS;
    }
}
