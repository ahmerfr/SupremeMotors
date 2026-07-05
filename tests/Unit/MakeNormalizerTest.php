<?php

namespace Tests\Unit;

use App\Services\MakeNormalizer;
use PHPUnit\Framework\TestCase;

class MakeNormalizerTest extends TestCase
{
    private MakeNormalizer $n;

    protected function setUp(): void
    {
        $this->n = new MakeNormalizer;
    }

    /** @dataProvider cases */
    public function test_canonical(?string $expected, ?string $input): void
    {
        $this->assertSame($expected, $this->n->canonical($input));
    }

    public static function cases(): array
    {
        return [
            // Mercedes family collapses to one
            ['Mercedes Benz', 'Mercedes-Benz'],
            ['Mercedes Benz', 'Mercedes Benz'],
            ['Mercedes Benz', 'Mercedes-AMG'],
            ['Mercedes Benz', 'Mercedes-Maybach'],
            ['Mercedes Benz', 'mercedes'],
            // other known variants
            ['Alfa Romeo', 'Alfa'],
            ['Kia', 'Kia Motors'],
            ['Volkswagen', 'VW'],
            ['Land Rover', 'Range Rover'],
            ['Mini', 'Mini Cooper'],
            // unknown/new makes pass through untouched
            ['Jaguar', 'Jaguar'],
            ['Mitsubishi', 'Mitsubishi'],
            ['Chery', 'Chery'],
            ['Toyota', ' Toyota '],
            [null, null],
            [null, '   '],
        ];
    }
}
