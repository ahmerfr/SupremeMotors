<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SwapToCdn extends Command
{
    protected $signature = 'products:swap-to-cdn';

    protected $description = 'Rewrite image URLs from the source CDNs to our Bunny pull zones (set-based, seconds; originals kept in *_source columns)';

    /** origin prefix => our pull zone (must match bunny:setup-zones) */
    public const MAP = [
        'https://img.linemedia.com' => 'https://sm-linemedia.b-cdn.net',
        'https://image.made-in-china.com' => 'https://sm-madeinchina.b-cdn.net',
        'https://www.tc-v.com' => 'https://sm-tcv.b-cdn.net',
    ];

    public function handle(): int
    {
        foreach (self::MAP as $origin => $cdn) {
            $host = parse_url($origin, PHP_URL_HOST);
            // JSON columns store URLs with escaped slashes (https:\/\/...),
            // so replace both the plain and the escaped form.
            $escOrigin = str_replace('/', '\\/', $origin);
            $escCdn = str_replace('/', '\\/', $cdn);

            $fronts = DB::update(
                'UPDATE products
                 SET front_image_source = COALESCE(front_image_source, front_image),
                     front_image = REPLACE(front_image, ?, ?)
                 WHERE front_image LIKE ?',
                [$origin, $cdn, $origin . '%']
            );

            $galleries = DB::update(
                'UPDATE products
                 SET other_images_source = COALESCE(other_images_source, other_images),
                     other_images = REPLACE(REPLACE(other_images, ?, ?), ?, ?)
                 WHERE other_images LIKE ?',
                [$origin, $cdn, $escOrigin, $escCdn, '%' . $host . '%']
            );

            $this->info(str_pad($host, 26) . " fronts: {$fronts}  galleries: {$galleries}");
        }

        $leftover = DB::table('products')
            ->where('front_image', 'like', 'http%')
            ->where('front_image', 'not like', '%.b-cdn.net%')
            ->count();
        $this->info("remote fronts not on our CDN after swap: {$leftover}");

        return self::SUCCESS;
    }
}
