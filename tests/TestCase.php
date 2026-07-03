<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never let tests write CDN pipeline state (checkpoints, done markers)
        // into the real storage/app/cdn — the production pipeline reads it.
        config(['cdn.state_dir' => sys_get_temp_dir() . '/sm-cdn-state-' . getmypid() . '-' . uniqid()]);
    }
}
