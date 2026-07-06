<?php

namespace Tests\Feature;

use App\Services\JaftimParser;
use Tests\TestCase;

class ScrapeJaftimTest extends TestCase
{
    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/jaftim_listing.html'));
    }

    private function rows(): array
    {
        return (new JaftimParser)->parseListing($this->fixture());
    }

    public function test_parses_the_full_page_of_cars(): void
    {
        $rows = $this->rows();
        $this->assertGreaterThanOrEqual(300, count($rows));
        foreach (array_slice($rows, 0, 50) as $r) {
            $this->assertNotEmpty($r['title']);
            $this->assertStringContainsString('erp.jaftim.com', $r['front_image']);
            $this->assertStringContainsString('jaftim.com/used-', $r['product_link']);
            $this->assertContains($r['category_id'], [4, 13, 20, 63]);
            $this->assertSame('Used', $r['condition']);
        }
    }

    public function test_maps_a_priced_car_with_specs(): void
    {
        // first car on the page that has a real USD price
        $r = collect($this->rows())->firstWhere(fn ($x) => $x['price_usd'] > 0);
        $this->assertNotNull($r);
        $this->assertNotEmpty($r['make']);
        $this->assertNotEmpty($r['model']);
        $this->assertIsInt($r['price_usd']);
        $this->assertStringContainsString('/' . $r['stock_id'] . '/f.jpg', $r['front_image']);
        $this->assertStringContainsString('/' . $r['stock_id'], $r['product_link']);
        $this->assertStringContainsString('<li><strong>', $r['product_details']);
        $this->assertContains($r['steering'], ['Right', 'Left', null]);
    }

    public function test_most_cars_have_a_usd_price(): void
    {
        $rows = $this->rows();
        $priced = collect($rows)->where('price_usd', '>', 0)->count();
        // majority priced (rest are POA -> 0/Enquire)
        $this->assertGreaterThan(count($rows) * 0.9, $priced);
    }

    public function test_price_is_integer_usd_and_poa_is_zero(): void
    {
        foreach ($this->rows() as $r) {
            $this->assertIsInt($r['price_usd']);
            $this->assertGreaterThanOrEqual(0, $r['price_usd']);
        }
    }

    public function test_gallery_images_parsed_from_detail_ordered_front_first(): void
    {
        $html = '<img src="https://erp.jaftim.com/storage/app/public/stock/33090/2.jpg">'
            . '<img src="https://erp.jaftim.com/storage/app/public/stock/33090/f.jpg">'
            . '<img src="https://erp.jaftim.com/storage/app/public/stock/33090/1.jpg">'
            . '<img src="https://erp.jaftim.com/storage/app/public/stock/33090/10.jpg">';
        $imgs = (new JaftimParser)->parseGalleryImages($html, '33090');
        $this->assertStringEndsWith('/f.jpg', $imgs[0]);         // front first
        $this->assertStringEndsWith('/1.jpg', $imgs[1]);         // then numeric ascending
        $this->assertStringEndsWith('/2.jpg', $imgs[2]);
        $this->assertStringEndsWith('/10.jpg', $imgs[3]);
    }
}
