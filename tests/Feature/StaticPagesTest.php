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

    public function test_terms_page_renders(): void
    {
        $this->get('/terms-condition')->assertOk()->assertInertia(fn (Assert $page) => $page->component('TermsCondition'));
    }

    public function test_bank_details_page_renders(): void
    {
        $this->get('/bank-details')->assertOk()->assertInertia(fn (Assert $page) => $page->component('BankDetails'));
    }
}
