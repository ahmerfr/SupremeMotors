<?php

namespace Tests\Unit;

use App\Support\ProductDetailsParser;
use PHPUnit\Framework\TestCase;

class ProductDetailsParserTest extends TestCase
{
    private function html(array $pairs): string
    {
        $lis = '';
        foreach ($pairs as $k => $v) {
            $lis .= "<li><strong>{$k}:</strong> {$v}</li>";
        }

        return "<ul>{$lis}</ul>";
    }

    public function test_parses_full_autoline_style_document(): void
    {
        $out = ProductDetailsParser::parse($this->html([
            'Brand' => 'Shacman',
            'Model' => '7fd30',
            'Model code' => '8UCCZF',
            'Registration Year / Month' => '2014/09',
            'Engine capacity (Displacement)' => '2,000cc',
            'Mileage' => '75,628 km',
            'Fuel' => 'diesel',
            'Transmission' => 'Automatic',
            'Condition' => 'used',
            'Exterior Color' => 'deep blue',
            'Steering' => 'Right',
            'Number of seats' => '5',
            'Drive type' => '4wheel drive',
        ]));

        $this->assertSame('7fd30', $out['model']);
        $this->assertSame('8UCCZF', $out['model_code']);
        $this->assertSame(2014, $out['year']);
        $this->assertSame(2000, $out['engine_cc']);
        $this->assertSame(75628, $out['mileage_km']);
        $this->assertSame('Diesel', $out['fuel']);
        $this->assertSame('Automatic', $out['transmission']);
        $this->assertSame('Used', $out['condition']);
        $this->assertSame('Deep Blue', $out['color']);
        $this->assertSame('Right', $out['steering']);
        $this->assertSame(5, $out['seats']);
        $this->assertSame('4WD', $out['drive_type']);
    }

    public function test_junk_values_become_null(): void
    {
        $out = ProductDetailsParser::parse($this->html([
            'Mileage' => '-',
            'Fuel' => 'N/A',
            'Exterior Color' => '',
            'Registration Year / Month' => 'Confirm with the Seller',
        ]));

        $this->assertNull($out['mileage_km']);
        $this->assertNull($out['fuel']);
        $this->assertNull($out['color']);
        $this->assertNull($out['year']);
    }

    public function test_year_priority_and_sanity(): void
    {
        // registration year wins over year of manufacture
        $out = ProductDetailsParser::parse($this->html([
            'Registration Year / Month' => '2013/02',
            'Year of manufacture' => '2017',
        ]));
        $this->assertSame(2013, $out['year']);

        // falls back to year of manufacture, then first registration
        $out = ProductDetailsParser::parse($this->html(['Year of manufacture' => '2017']));
        $this->assertSame(2017, $out['year']);

        $out = ProductDetailsParser::parse($this->html(['First registration' => '2021-04-04']));
        $this->assertSame(2021, $out['year']);

        // out-of-range rejected
        $out = ProductDetailsParser::parse($this->html(['Year of manufacture' => '1899']));
        $this->assertNull($out['year']);
    }

    public function test_colour_fallback_and_mileage_miles_conversion(): void
    {
        $out = ProductDetailsParser::parse($this->html([
            'Colour' => 'golden',
            'Mileage' => '10,000 miles',
        ]));
        $this->assertSame('Golden', $out['color']);
        $this->assertSame(16090, $out['mileage_km']);
    }

    public function test_engine_litre_form(): void
    {
        $out = ProductDetailsParser::parse($this->html(['Engine capacity (Displacement)' => '2.0L']));
        $this->assertSame(2000, $out['engine_cc']);
    }

    public function test_canonicalizes_messy_scraped_values(): void
    {
        $out = ProductDetailsParser::parse($this->html([
            'Fuel' => 'Gasoline/petrol',
            'Transmission' => 'Hw19710, 10 Forward And 2 Reve',
            'Steering' => 'Zf Power Steering',
            'Drive type' => '6*4',
        ]));
        $this->assertSame('Petrol', $out['fuel']);
        $this->assertSame('Manual', $out['transmission']);
        $this->assertNull($out['steering']);
        $this->assertSame('6*4', $out['drive_type']);

        $out = ProductDetailsParser::parse($this->html([
            'Fuel' => 'Diesel/electro',
            'Transmission' => 'CVT',
            'Steering' => 'Left/right',
        ]));
        $this->assertSame('Hybrid', $out['fuel']);
        $this->assertSame('CVT', $out['transmission']);
        $this->assertNull($out['steering']);
    }

    public function test_absurd_numeric_values_rejected(): void
    {
        $out = ProductDetailsParser::parse($this->html([
            'Mileage' => '12,850,000,000 km',
            'Engine capacity (Displacement)' => '999,999,999cc',
        ]));
        $this->assertNull($out['mileage_km']);
        $this->assertNull($out['engine_cc']);
    }

    public function test_values_clamped_to_column_widths(): void
    {
        $out = ProductDetailsParser::parse($this->html([
            'Condition' => 'Used (accident not repaired) plus extra words beyond forty chars',
        ]));
        $this->assertLessThanOrEqual(40, mb_strlen($out['condition']));
    }

    public function test_empty_or_unrelated_html_returns_all_nulls(): void
    {
        $out = ProductDetailsParser::parse('<p>plain text no attributes</p>');
        $this->assertSame([], array_filter($out, fn ($v) => $v !== null));
        $this->assertArrayHasKey('model', $out);
        $this->assertArrayHasKey('drive_type', $out);
    }
}
