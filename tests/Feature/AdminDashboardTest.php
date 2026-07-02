<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Newsletter;
use App\Models\Products;
use App\Models\QueryForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_dashboard_with_live_stats(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Categories::create(['cat_title' => 'Cars', 'type' => 'category', 'description' => '--']);
        Products::create([
            'title' => 'Own Car', 'website' => 'suprememotors', 'category_id' => $category->id,
            'price' => 5000, 'country' => 'Japan', 'front_image' => 'x.jpg', 'other_images' => [],
            'product_details' => '<p>x</p>', 'stock_code' => 'SM1',
        ]);
        Products::create([
            'title' => 'Scraped Car', 'website' => 'tcv', 'category_id' => $category->id,
            'price' => 100, 'country' => 'China', 'front_image' => 'y.jpg', 'other_images' => [],
            'product_details' => '<p>y</p>', 'stock_code' => 'SM2',
        ]);
        Newsletter::create(['email' => 'a@b.com']);
        $q = new QueryForm;
        $q->company = 'ACME';
        $q->contact_name = 'Bob';
        $q->email = 'bob@acme.com';
        $q->save();

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.own_products', 1)
            ->where('stats.total_products', 2)
            ->where('stats.newsletter', 1)
            ->where('stats.queries', 1)
            ->has('recent_queries', 1)
            ->has('recent_products', 1)
            ->has('by_country')
            ->has('top_makes')
        );
    }

    public function test_regular_user_cannot_see_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/dashboard')->assertRedirect('/');
    }
}
