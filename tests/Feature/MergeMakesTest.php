<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\Products;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeMakesTest extends TestCase
{
    use RefreshDatabase;

    public function test_merges_variant_makes_into_canonical(): void
    {
        $mb = Categories::create(['cat_title' => 'Mercedes Benz', 'type' => 'make'])->id;
        $mbHyphen = Categories::create(['cat_title' => 'Mercedes-Benz', 'type' => 'make'])->id;
        $amg = Categories::create(['cat_title' => 'Mercedes-AMG', 'type' => 'make'])->id;
        $kiaMotors = Categories::create(['cat_title' => 'Kia Motors', 'type' => 'make'])->id;
        $kia = Categories::create(['cat_title' => 'Kia', 'type' => 'make'])->id;
        $toyota = Categories::create(['cat_title' => 'Toyota', 'type' => 'make'])->id;

        $c1 = Products::create(['title' => 'a', 'make_id' => $mbHyphen, 'website' => 'autotraderza'])->id;
        $c2 = Products::create(['title' => 'b', 'make_id' => $amg, 'website' => 'autotraderza'])->id;
        $c3 = Products::create(['title' => 'c', 'make_id' => $kiaMotors, 'website' => 'autotraderza'])->id;
        $c4 = Products::create(['title' => 'd', 'make_id' => $toyota, 'website' => 'autotraderza'])->id;

        $this->artisan('products:merge-makes')->assertSuccessful();

        // variant categories gone
        $this->assertNull(Categories::find($mbHyphen));
        $this->assertNull(Categories::find($amg));
        $this->assertNull(Categories::find($kiaMotors));
        // canonical ones remain
        $this->assertNotNull(Categories::find($mb));
        $this->assertNotNull(Categories::find($kia));

        // cars reassigned to the canonical make
        $this->assertSame($mb, Products::find($c1)->make_id);
        $this->assertSame($mb, Products::find($c2)->make_id);
        $this->assertSame($kia, Products::find($c3)->make_id);
        $this->assertSame($toyota, Products::find($c4)->make_id); // untouched
    }

    public function test_dry_run_changes_nothing(): void
    {
        Categories::create(['cat_title' => 'Mercedes Benz', 'type' => 'make']);
        $mbHyphen = Categories::create(['cat_title' => 'Mercedes-Benz', 'type' => 'make'])->id;
        $id = Products::create(['title' => 'a', 'make_id' => $mbHyphen, 'website' => 'autotraderza'])->id;

        $this->artisan('products:merge-makes', ['--dry-run' => true])->assertSuccessful();

        $this->assertNotNull(Categories::find($mbHyphen));
        $this->assertSame($mbHyphen, Products::find($id)->make_id);
    }
}
