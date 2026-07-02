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

    public function test_parses_doors_from_both_formats(): void
    {
        $out = ProductDetailsParser::parse($this->html(['Door' => '5']));
        $this->assertSame(5, $out['doors']);

        // Own-site format: "Doors : 4D"; junk rejected.
        $out = ProductDetailsParser::parse('<p>Doors&nbsp;:&nbsp;4D</p>');
        $this->assertSame(4, $out['doors']);

        $out = ProductDetailsParser::parse($this->html(['Door' => '6mm']));
        $this->assertNull($out['doors']);

        $out = ProductDetailsParser::parse($this->html(['Door' => '0']));
        $this->assertNull($out['doors']);
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
        $this->assertSame('6x4', $out['drive_type']);

        $out = ProductDetailsParser::parse($this->html([
            'Fuel' => 'Diesel/electro',
            'Transmission' => 'CVT',
            'Steering' => 'Left/right',
        ]));
        $this->assertSame('Hybrid', $out['fuel']);
        $this->assertSame('CVT', $out['transmission']);
        $this->assertNull($out['steering']);
    }

    public function test_drive_type_axle_configs_and_leaked_steering(): void
    {
        // "6X4", "6×4", "6*4" are the same config — one canonical spelling.
        foreach (['6X4', '6×4', '6*4'] as $variant) {
            $out = ProductDetailsParser::parse($this->html(['Drive type' => $variant]));
            $this->assertSame('6x4', $out['drive_type'], "variant: {$variant}");
        }

        $out = ProductDetailsParser::parse($this->html(['Drive type' => 'Front-wheel drive']));
        $this->assertSame('FWD', $out['drive_type']);

        $out = ProductDetailsParser::parse($this->html(['Drive type' => 'Rear-wheel drive']));
        $this->assertSame('RWD', $out['drive_type']);

        // Steering-side text does not belong in drive_type.
        $out = ProductDetailsParser::parse($this->html(['Drive type' => 'Right Or Left Hand Drive, LHD']));
        $this->assertNull($out['drive_type']);
    }

    public function test_ambiguous_condition_values_become_null(): void
    {
        foreach (['New/used', 'Both New And Used', "According To Customer′s Choice New Or Used"] as $junk) {
            $out = ProductDetailsParser::parse($this->html(['Condition' => $junk]));
            $this->assertNull($out['condition'], "value: {$junk}");
        }

        $out = ProductDetailsParser::parse($this->html(['Condition' => 'Used (no accident)']));
        $this->assertSame('Used (no Accident)', $out['condition']);
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

    public function test_parses_suprememotors_paragraph_format(): void
    {
        // The site's own products use <p>Key&nbsp;:&nbsp;Value</p> — no <strong>.
        $html = '<p>TOYOTA&nbsp;MARK&nbsp;X&nbsp;250G</p><p>Year&nbsp;:&nbsp;2010</p>'
            .'<p>Color&nbsp;:&nbsp;GREY</p><p>Mileage&nbsp;:&nbsp;60,388&nbsp;km</p>'
            .'<p>Steering&nbsp;:&nbsp;Right</p><p>Transmission&nbsp;:&nbsp;AT</p>'
            .'<p>Fuel&nbsp;:&nbsp;GASOLINE</p><p>Drive&nbsp;System&nbsp;:&nbsp;2WD</p>'
            .'<p>Doors&nbsp;:&nbsp;4D</p><p>Displacement&nbsp;:&nbsp;&nbsp;2500cc</p>'
            .'<p>Chassis&nbsp;No&nbsp;:&nbsp;GRX130</p><p></p>';

        $out = ProductDetailsParser::parse($html);

        $this->assertSame(2010, $out['year']);
        $this->assertSame('Grey', $out['color']);
        $this->assertSame(60388, $out['mileage_km']);
        $this->assertSame('Right', $out['steering']);
        $this->assertSame('Automatic', $out['transmission']);
        $this->assertSame('Petrol', $out['fuel']);
        $this->assertSame('2WD', $out['drive_type']);
        $this->assertSame(2500, $out['engine_cc']);
        $this->assertSame('GRX130', $out['model_code']);
    }

    public function test_parses_tab_separated_and_alias_keys(): void
    {
        // Another in-house variant: tab-separated pairs, keys with suffixes.
        $html = "<p>Chassis&nbsp;No.\tMR0KA3CD301251371\t</p><p>Steering\tRight</p>"
            ."<p>Engine&nbsp;Size\t2,800cc\t</p><p>Ext.&nbsp;Color\tGray</p>"
            ."<p>Fuel\tDiesel</p><p>Seats\t5</p>";
        $out = ProductDetailsParser::parse($html);
        $this->assertSame('MR0KA3CD301251371', $out['model_code']);
        $this->assertSame('Right', $out['steering']);
        $this->assertSame(2800, $out['engine_cc']);
        $this->assertSame('Gray', $out['color']);
        $this->assertSame('Diesel', $out['fuel']);
        $this->assertSame(5, $out['seats']);

        // Colon variant with "Engine Size:" / "Fuel Type:" keys.
        $html = '<p>Engine&nbsp;Size:&nbsp;4,200&nbsp;cc</p><p>Fuel&nbsp;Type:&nbsp;DIESEL</p><p>Engine:&nbsp;1,500cc</p>';
        $out = ProductDetailsParser::parse($html);
        $this->assertSame(4200, $out['engine_cc']);
        $this->assertSame('Diesel', $out['fuel']);
    }

    public function test_alias_keys_for_transmission_and_drive_type(): void
    {
        $out = ProductDetailsParser::parse($this->html(['Transmission Type' => 'Manual']));
        $this->assertSame('Manual', $out['transmission']);

        $out = ProductDetailsParser::parse($this->html(['Axle Configuration' => '8x4']));
        $this->assertSame('8x4', $out['drive_type']);

        $out = ProductDetailsParser::parse($this->html(['Drive Wheel' => '6×4']));
        $this->assertSame('6x4', $out['drive_type']);
    }

    public function test_truck_and_machinery_fields(): void
    {
        $out = ProductDetailsParser::parse($this->html([
            'Number of axles' => '3',
            'Load capacity' => '3,000 kg',
            'Power' => '110 kW (150 HP)',
            'Emission standard' => 'Euro 3',
            'Running hours' => '3,000 m/h',
        ]));
        $this->assertSame(3, $out['axles']);
        $this->assertSame(3000, $out['load_capacity_kg']);
        $this->assertSame(150, $out['power_hp']);
        $this->assertSame('Euro 3', $out['emission_standard']);
        $this->assertSame(3000, $out['running_hours']);
    }

    public function test_power_load_and_emission_variants(): void
    {
        // kW only -> converted; tons -> kg; roman euro; hp ranges take first.
        $out = ProductDetailsParser::parse($this->html([
            'Power' => '110 kW',
            'Payload' => '40~120 Tons',
            'Euro' => 'Euro II',
        ]));
        $this->assertSame(148, $out['power_hp']);
        $this->assertSame(40000, $out['load_capacity_kg']);
        $this->assertSame('Euro 2', $out['emission_standard']);

        $out = ProductDetailsParser::parse($this->html([
            'Horsepower' => '351-450hp',
            'Emission' => 'euro 2/3/4/5',
        ]));
        $this->assertSame(351, $out['power_hp']);
        $this->assertNull($out['emission_standard']);
    }

    public function test_empty_or_unrelated_html_returns_all_nulls(): void
    {
        $out = ProductDetailsParser::parse('<p>plain text no attributes</p>');
        $this->assertSame([], array_filter($out, fn ($v) => $v !== null));
        $this->assertArrayHasKey('model', $out);
        $this->assertArrayHasKey('drive_type', $out);
    }
}
