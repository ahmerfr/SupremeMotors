<?php

namespace Tests\Feature;

use App\Services\AutolineDetailParser;
use Tests\TestCase;

class ScrapeAutolineTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/autoline/{$name}"));
    }

    private function parsed(): array
    {
        $url = 'https://autoline.info/-/sale/semi-integrated-motorhomes/Carthago/C-tourer-T-143-LE--26041510015508318700';

        return (new AutolineDetailParser)->parse($this->fixture('detail.html'), $url);
    }

    public function test_core_fields_from_json_ld(): void
    {
        $r = $this->parsed();
        $this->assertSame('Carthago C-tourer T 143 LE semi-integrated motorhome', $r['title']);
        $this->assertSame('Carthago', $r['brand']);
        $this->assertSame(74799.0, $r['price_eur']);
        $this->assertSame('EUR', $r['currency']);
        $this->assertSame('26041510015508318700', $r['listing_id']);
    }

    public function test_full_gallery_extracted(): void
    {
        $r = $this->parsed();
        $this->assertGreaterThanOrEqual(10, count($r['images']));
        $this->assertStringStartsWith('https://img.linemedia.com', $r['front_image']);
        // every image belongs to this advert
        foreach ($r['images'] as $img) {
            $this->assertStringContainsString('--26041510015508318700', $img);
        }
    }

    public function test_spec_table_maps_to_columns(): void
    {
        $r = $this->parsed();
        $this->assertSame(2020, $r['year']);
        $this->assertSame(42533, $r['mileage_km']);
        $this->assertStringContainsString('<li><strong>', $r['product_details']);
        $this->assertStringContainsString('Year of manufacture', $r['product_details']);
    }

    public function test_dedup_id_from_url_and_image_match(): void
    {
        $p = new AutolineDetailParser;
        $fromUrl = $p->listingId('https://autoline.info/-/sale/x/Y/Z--26041510015508318700');
        $fromImg = $p->listingIdFromImage('https://img.linemedia.com/img/s/camper---1776237900781991948_big--26041510015508318700.jpg');
        $this->assertSame('26041510015508318700', $fromUrl);
        $this->assertSame($fromUrl, $fromImg);
    }

    public function test_returns_null_on_garbage(): void
    {
        $this->assertNull((new AutolineDetailParser)->parse('<html>nope</html>', 'https://autoline.info/x'));
    }

    public function test_rejects_spare_parts(): void
    {
        // a muffler advert: @type "Product" (no "Vehicle") -> must be skipped
        $url = 'https://autoline.info/-/sale/mufflers/truck/Renault/Silencer--26070410323932917500';
        $this->assertNull((new AutolineDetailParser)->parse($this->fixture('part.html'), $url));
    }
}
