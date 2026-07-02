<?php

namespace Tests\Unit;

use App\Support\MongoExtendedJson;
use PHPUnit\Framework\TestCase;

class MongoExtendedJsonTest extends TestCase
{
    public function test_flattens_oid(): void
    {
        $doc = ['_id' => ['$oid' => '67e7cd7b6a5af0e3790dbc6c'], 'title' => 'x'];
        $out = MongoExtendedJson::normalize($doc);
        $this->assertSame('67e7cd7b6a5af0e3790dbc6c', $out['_id']);
        $this->assertSame('x', $out['title']);
    }

    public function test_converts_date_number_long_ms_to_datetime_string(): void
    {
        $doc = ['created_at' => ['$date' => ['$numberLong' => '1743244666940']]];
        $out = MongoExtendedJson::normalize($doc);
        $this->assertSame('2025-03-29 10:37:46', $out['created_at']);
    }

    public function test_converts_iso_date_string(): void
    {
        $doc = ['created_at' => ['$date' => '2025-03-29T10:37:46.940Z']];
        $out = MongoExtendedJson::normalize($doc);
        $this->assertSame('2025-03-29 10:37:46', $out['created_at']);
    }

    public function test_converts_numeric_wrappers(): void
    {
        $doc = [
            'a' => ['$numberInt' => '50'],
            'b' => ['$numberLong' => '1750778211'],
            'c' => ['$numberDouble' => '1.5'],
        ];
        $out = MongoExtendedJson::normalize($doc);
        $this->assertSame(50, $out['a']);
        $this->assertSame(1750778211, $out['b']);
        $this->assertSame(1.5, $out['c']);
    }

    public function test_recurses_into_plain_arrays_and_leaves_scalars(): void
    {
        $doc = [
            'images' => ['a.jpg', 'b.jpg'],
            'nested' => ['id' => ['$oid' => 'abcabcabcabcabcabcabcabc']],
            'flag' => true,
        ];
        $out = MongoExtendedJson::normalize($doc);
        $this->assertSame(['a.jpg', 'b.jpg'], $out['images']);
        $this->assertSame('abcabcabcabcabcabcabcabc', $out['nested']['id']);
        $this->assertTrue($out['flag']);
    }

    public function test_parse_price_variants(): void
    {
        $this->assertSame(24920.0, MongoExtendedJson::parsePrice('24,920'));
        $this->assertSame(1234.0, MongoExtendedJson::parsePrice('1234 USD'));
        $this->assertSame(0.0, MongoExtendedJson::parsePrice(''));
        $this->assertSame(0.0, MongoExtendedJson::parsePrice(null));
        $this->assertSame(52000.5, MongoExtendedJson::parsePrice(52000.5));
    }
}
