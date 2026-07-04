<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Products;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_page_ships_products_and_facets(): void
    {
        $category = Categories::create(['cat_title' => 'Cars', 'type' => 'category', 'description' => '--']);
        $make = Categories::create(['cat_title' => 'Toyota', 'type' => 'make', 'description' => '--']);
        Products::create([
            'title' => 'Toyota Corolla', 'website' => 'tcv', 'category_id' => $category->id,
            'make_id' => $make->id, 'price' => 5000, 'country' => 'Japan',
            'front_image' => 'https://sm-tcv.b-cdn.net/img/a.jpg', 'other_images' => [],
            'product_details' => '<p>x</p>', 'stock_code' => 'SM-1',
            'fuel' => 'Petrol', 'transmission' => 'AT', 'body_style' => 'Sedan', 'year' => 2018,
        ]);

        $this->get('/inventory')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Shop')
            ->has('products.data', 1)
            ->has('facets.countries', 1)
            ->where('facets.countries.0.value', 'Japan')
            ->where('facets.fuels.0.value', 'Petrol')
            ->where('facets.body_styles.0.value', 'Sedan')
            ->where('makes.0.products_count', 1)
        );
    }
}
