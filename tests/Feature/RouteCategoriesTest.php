<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Products;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfills_existing_rows_into_correct_categories(): void
    {
        $cars = Categories::create(['cat_title' => 'Cars', 'type' => 'category'])->id;
        $trucks = Categories::create(['cat_title' => 'Trucks', 'type' => 'category'])->id;
        $comm = Categories::create(['cat_title' => 'Commercial Vehicles', 'type' => 'category'])->id;
        $buses = Categories::create(['cat_title' => 'Buses', 'type' => 'category'])->id;

        // all start mis-filed under Cars (how the pre-router scrape banked them)
        $hilux = Products::create(['website' => 'perfectmotors', 'title' => '2018 Toyota Hilux', 'body_style' => 'Double cab', 'category_id' => $cars])->id;
        $hiace = Products::create(['website' => 'perfectmotors', 'title' => '2007 Toyota Hiace', 'category_id' => $cars])->id;
        $coaster = Products::create(['website' => 'perfectmotors', 'title' => '2010 Toyota Coaster', 'category_id' => $cars])->id;
        $prius = Products::create(['website' => 'perfectmotors', 'title' => '2012 Toyota Prius', 'body_style' => 'Hatchback', 'category_id' => $cars])->id;
        // a different source must be untouched
        $other = Products::create(['website' => 'tcv', 'title' => '2018 Toyota Hilux', 'category_id' => $cars])->id;

        $this->artisan('products:route-categories', ['--website' => 'perfectmotors'])->assertSuccessful();

        $this->assertSame($trucks, Products::find($hilux)->category_id);
        $this->assertSame($comm, Products::find($hiace)->category_id);
        $this->assertSame($buses, Products::find($coaster)->category_id);
        $this->assertSame($cars, Products::find($prius)->category_id);   // stays Cars
        $this->assertSame($cars, Products::find($other)->category_id);   // other source untouched
    }

    public function test_dry_run_changes_nothing(): void
    {
        $cars = Categories::create(['cat_title' => 'Cars', 'type' => 'category'])->id;
        Categories::create(['cat_title' => 'Trucks', 'type' => 'category']);
        $id = Products::create(['website' => 'perfectmotors', 'title' => 'Hilux', 'body_style' => 'Double cab', 'category_id' => $cars])->id;

        $this->artisan('products:route-categories', ['--website' => 'perfectmotors', '--dry-run' => true])->assertSuccessful();

        $this->assertSame($cars, Products::find($id)->category_id); // unchanged
    }
}
