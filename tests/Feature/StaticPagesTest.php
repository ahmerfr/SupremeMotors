<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaticPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_faqs_page_renders(): void
    {
        $this->get('/faqs')->assertOk()->assertInertia(fn (Assert $page) => $page->component('FAQs'));
    }
}
