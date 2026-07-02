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

    public function test_no_attribute_params_returns_everything(): void
    {
        $this->makeProduct(['title' => 'A']);
        $this->makeProduct(['title' => 'B']);

        $response = $this->getJson('/inventory/listing');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}
