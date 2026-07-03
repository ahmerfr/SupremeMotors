<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminMakesSplitTest extends TestCase
{
    use RefreshDatabase;

    private function seedBoth(): void
    {
        Categories::create(['cat_title' => 'Trucks', 'type' => 'category', 'description' => '--']);
        Categories::create(['cat_title' => 'Toyota', 'type' => 'make', 'description' => '--']);
        Categories::create(['cat_title' => 'Hino', 'type' => 'make', 'description' => '--']);
    }

    public function test_categories_tab_lists_only_categories(): void
    {
        $this->seedBoth();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/categories')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Categories/Index')
            ->where('type', 'category')
            ->has('categories.data', 1)
            ->where('categories.data.0.cat_title', 'Trucks')
        );
    }

    public function test_makes_tab_lists_only_makes(): void
    {
        $this->seedBoth();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/makes')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Categories/Index')
            ->where('type', 'make')
            ->has('categories.data', 2)
        );
    }

    public function test_make_create_page_carries_make_type(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/makes/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Categories/Create')
            ->where('type', 'make')
        );
    }

    public function test_store_rejects_unknown_type(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/admin/categories/store', [
            'title' => 'Weird',
            'type' => 'banana',
        ])->assertStatus(422);
    }
}
