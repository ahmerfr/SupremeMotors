<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Products;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingAttributeFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $attrs): Products
    {
        $category = Categories::firstOrCreate(
            ['cat_title' => 'Cars'],
            ['type' => 'category', 'description' => '--']
        );

        return Products::create(array_merge([
            'title' => 'Test Car',
            'category_id' => $category->id,
            'price' => 5000,
            'country' => 'Japan',
            'website' => 'suprememotors',
            'front_image' => 'product_images/x.jpg',
            'other_images' => [],
            'product_details' => '<ul><li><strong>Fuel:</strong> Petrol</li></ul>',
        ], $attrs));
    }

    public function test_filters_by_year_range_and_fuel(): void
    {
        $this->makeProduct(['title' => 'Old Diesel', 'year' => 2005, 'fuel' => 'Diesel']);
        $this->makeProduct(['title' => 'New Petrol', 'year' => 2021, 'fuel' => 'Petrol']);

        $response = $this->getJson('/inventory/listing?year_from=2018&year_to=2024&fuel=Petrol');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('New Petrol', $data[0]['title']);
    }

    public function test_filters_by_mileage_range_in_search_branch(): void
    {
        $this->makeProduct(['title' => 'Low KM', 'mileage_km' => 30000]);
        $this->makeProduct(['title' => 'High KM', 'mileage_km' => 250000]);

        $response = $this->getJson('/inventory/listing?type=search&mileage_min=1000&mileage_max=50000');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Low KM', $data[0]['title']);
    }

    public function test_filters_by_comma_separated_transmission_list(): void
    {
        $this->makeProduct(['title' => 'Auto', 'transmission' => 'Automatic']);
        $this->makeProduct(['title' => 'Stick', 'transmission' => 'Manual']);
        $this->makeProduct(['title' => 'Belt', 'transmission' => 'CVT']);

        $response = $this->getJson('/inventory/listing?transmission=Automatic,CVT');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_filters_by_doors(): void
    {
        $this->makeProduct(['title' => 'Hatch', 'doors' => 5]);
        $this->makeProduct(['title' => 'Coupe', 'doors' => 2]);

        $response = $this->getJson('/inventory/listing?doors=5');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Hatch', $data[0]['title']);
    }

    public function test_no_attribute_params_returns_everything(): void
    {
        $this->makeProduct(['title' => 'A']);
        $this->makeProduct(['title' => 'B']);

        $response = $this->getJson('/inventory/listing');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_body_style_accepts_comma_list(): void
    {
        $this->makeProduct(['title' => 'Sedan A', 'body_style' => 'Sedan']);
        $this->makeProduct(['title' => 'SUV B', 'body_style' => 'SUV']);
        $this->makeProduct(['title' => 'Bus C', 'body_style' => 'Bus']);

        $response = $this->getJson('/inventory/listing?body_style=Sedan,SUV');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_price_sort_sinks_enquire_rows(): void
    {
        $this->makeProduct(['title' => 'Enquire', 'price' => 0]);
        // priced in the DB but the cards hide non-tcv prices -> must sink too
        $this->makeProduct(['title' => 'Hidden price', 'price' => 100, 'website' => 'autoline']);
        $this->makeProduct(['title' => 'Cheap', 'price' => 1000, 'website' => 'tcv']);
        $this->makeProduct(['title' => 'Dear', 'price' => 9000, 'website' => 'tcv']);

        $titles = collect($this->getJson('/inventory/listing?sort=price_asc')->json('data'))->pluck('title')->all();

        $this->assertSame(['Cheap', 'Dear'], array_slice($titles, 0, 2));
        $this->assertContains('Enquire', array_slice($titles, 2));
        $this->assertContains('Hidden price', array_slice($titles, 2));
    }

    public function test_count_endpoint_matches_filters(): void
    {
        $this->makeProduct(['title' => 'Petrol A', 'fuel' => 'Petrol']);
        $this->makeProduct(['title' => 'Diesel B', 'fuel' => 'Diesel']);

        $this->getJson('/inventory/count?fuel=Petrol')->assertOk()->assertJson(['total' => 1]);
    }
}
