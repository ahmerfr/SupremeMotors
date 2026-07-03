<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scraped image URLs die as vehicles sell at the source; re-verify the
// homepage segments daily so dead cards never reach visitors.
Schedule::command('products:verify-images')->dailyAt('04:00');

// Refresh the long-TTL page caches in the background so the ~18s cold
// rebuild never lands on a visitor.
Schedule::command('pages:warm')->everyFifteenMinutes();
