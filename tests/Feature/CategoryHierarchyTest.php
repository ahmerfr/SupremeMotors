<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Products;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function seedTree(): array
    {
        $trucks = Categories::create(['cat_title' => 'Trucks', 'type' => 'category', 'description' => '--']);
        $dump = Categories::create(['cat_title' => 'DumpTruck', 'type' => 'category', 'description' => '--', 'parent_id' => $trucks->id]);
        $cars = Categories::create(['cat_title' => 'Cars', 'type' => 'category', 'description' => '--']);

        Products::create([
            'title' => 'Howo Dumper', 'website' => 'madeinchina', 'category_id' => $dump->id,
            'price' => 100, 'country' => 'China', 'front_image' => 'x.jpg', 'other_images' => [],
            'product_details' => '<p>x</p>', 'stock_code' => 'SM1',
        ]);
        Products::create([
            'title' => 'Plain Truck', 'website' => 'madeinchina', 'category_id' => $trucks->id,
            'price' => 100, 'country' => 'China', 'front_image' => 'y.jpg', 'other_images' => [],
            'product_details' => '<p>y</p>', 'stock_code' => 'SM2',
        ]);
        Products::create([
            'title' => 'Sedan Car', 'website' => 'tcv', 'category_id' => $cars->id,
            'price' => 100, 'country' => 'Japan', 'front_image' => 'z.jpg', 'other_images' => [],
            'product_details' => '<p>z</p>', 'stock_code' => 'SM3',
        ]);

        return [$trucks, $dump, $cars];
    }

    public function test_expand_with_children_includes_subcategories(): void
    {
        [$trucks, $dump] = $this->seedTree();

        $ids = Categories::expandWithChildren([$trucks->id]);

        $this->assertEqualsCanonicalizing([$trucks->id, $dump->id], $ids);
    }

    public function test_filtering_by_parent_category_includes_child_products(): void
    {
        [$trucks] = $this->seedTree();

        $response = $this->get('/inventory/listing?category=' . $trucks->id);

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertEqualsCanonicalizing(['Howo Dumper', 'Plain Truck'], $titles->all());
    }

    public function test_admin_category_list_rolls_up_parent_counts(): void
    {
        [$trucks, $dump] = $this->seedTree();
        $admin = \App\Models\User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/categories');

        $response->assertOk();
        $rows = collect($response->original->getData()['page']['props']['categories']['data']);
        $this->assertSame(2, $rows->firstWhere('id', $trucks->id)['products_count']);
        $this->assertSame(1, $rows->firstWhere('id', $dump->id)['products_count']);
    }

    public function test_homepage_categories_are_top_level_with_rolled_up_counts(): void
    {
        $this->seedTree();

        $response = $this->get('/');

        $response->assertOk();
        $categories = collect($response->original->getData()['page']['props']['categories']);
        $this->assertEqualsCanonicalizing(['Trucks', 'Cars'], $categories->pluck('cat_title')->all());
        $this->assertSame(2, $categories->firstWhere('cat_title', 'Trucks')['products_count']);
    }
}
