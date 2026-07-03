<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\AdminController;

Route::get('/', [DashboardController::class, 'home'])->name('home');
Route::get('/filter-bodystyle', [DashboardController::class, 'filter_bodystyle'])->name('filter-bodystyle');
Route::get('/home/body-type-products', [DashboardController::class, 'body_type_products'])->name('home.body-type-products');
Route::get('/filter-countryproducts', [DashboardController::class, 'filter_countryproducts'])->name('filter-countryproducts');

Route::prefix('category')->name('product-category.')->group(function () {
    Route::get(
        '/filter-countryproducts',
        [DashboardController::class, 'filter_countryproduct_category']
    )->name('filter-countryproducts');

    Route::get(
        '/{id}',
        [DashboardController::class, 'product_category']
    )->name('index');
});
Route::get('/contact-us', [DashboardController::class, 'contact_page'])->name('contact-us');
Route::get('/query-form', [DashboardController::class, 'queryform_page'])->name('query-form');
Route::post('/query-form-submit', [DashboardController::class, 'queryform_submit'])->name('query-form-submit');
Route::post('/newsletter/subscribe', [DashboardController::class, 'newsletter_subscribe'])->name('newsletter-subscribe');
Route::get('/terms-condition', [DashboardController::class, 'termscondition_page'])->name('terms-condition');
Route::get('/customer-review', [DashboardController::class, 'customerreview_page'])->name('customer-review');
Route::get('/faqs', [DashboardController::class, 'faqs_page'])->name('faqs');
Route::get('/customer-support', [DashboardController::class, 'customer_support_page'])->name('customer-support');
Route::get('/about-us', [DashboardController::class, 'about_page'])->name('about-us');
Route::get('/bank-details', [DashboardController::class, 'bankdetails_page'])->name('bank-details');
Route::get('/how-to-buy', [DashboardController::class, 'howtobuy_page'])->name('how-to-buy');
Route::post('/contact-save', [DashboardController::class, 'contact_save'])->name('contact-save');
Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [ShopController::class, 'home'])->name('index');
    Route::get('/listing', [ShopController::class, 'listing'])->name('listing');
    Route::get('/product-detail/{id}', [ShopController::class, 'product_detail'])->name('product-detail');
    Route::get('/product-detail-filter-country', [ShopController::class, 'filter_country_products'])->name('product-detail.filter-country');
});

Route::get('/search/products', [ShopController::class, 'search_products'])->name('search.products');

Route::prefix('blogs')->name('blogs.')->group(function () {
    Route::get('/', [DashboardController::class, 'blogs_page'])->name('index');
    Route::get('/{slug}', [DashboardController::class, 'blog_detail'])->name('detail');
});


Route::middleware(['auth', 'verified', 'role:admin,editor'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard route
    Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Admin-only: customer data and role management
    Route::middleware('role:admin')->group(function () {
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [AdminController::class, 'users_index'])->name('index');
            Route::get('/listing', [AdminController::class, 'users_listing'])->name('listing');
            Route::post('/', [AdminController::class, 'users_store'])->name('store');
            Route::patch('/{user}/role', [AdminController::class, 'users_update_role'])->name('role');
        });

        Route::prefix('newsletter')->name('newsletter.')->group(function () {
            Route::get('/', [AdminController::class, 'newsletter_index'])->name('index');
            Route::get('/listing', [AdminController::class, 'newsletter_listing'])->name('listing');
        });

        Route::prefix('query-form')->name('query-form.')->group(function () {
            Route::get('/', [AdminController::class, 'query_form_index'])->name('index');
            Route::get('/listing', [AdminController::class, 'query_form_listing'])->name('listing');
            Route::get('/view/{id}', [AdminController::class, 'query_form_view'])->name('view');
        });
    });

    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [AdminController::class, 'categories_index'])->name('index');
        Route::get('/listing', [AdminController::class, 'categories_listing'])->name('listing');
        Route::get('/create', [AdminController::class, 'categories_create'])->name('create');
        Route::post('/store', [AdminController::class, 'categories_store'])->name('store');
        Route::get('/edit/{id}', [AdminController::class, 'categories_edit'])->name('edit');
        Route::post('/update', [AdminController::class, 'categories_update'])->name('update');
    });

    // Same controller/pages as categories, scoped to type=make via route default.
    Route::prefix('makes')->name('makes.')->group(function () {
        Route::get('/', [AdminController::class, 'categories_index'])->name('index')->defaults('type', 'make');
        Route::get('/listing', [AdminController::class, 'categories_listing'])->name('listing')->defaults('type', 'make');
        Route::get('/create', [AdminController::class, 'categories_create'])->name('create')->defaults('type', 'make');
        Route::get('/edit/{id}', [AdminController::class, 'categories_edit'])->name('edit');
    });

    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [AdminController::class, 'products_index'])->name('index');
        Route::get('/listing', [AdminController::class, 'products_listing'])->name('listing');
        Route::get('/create', [AdminController::class, 'products_create'])->name('create');
        Route::post('/store', [AdminController::class, 'products_store'])->name('store');
        Route::get('/edit/{id}', [AdminController::class, 'products_edit'])->name('edit');
        Route::post('/update', [AdminController::class, 'products_update'])->name('update');
    });

    // Blogs
    Route::prefix('blogs')->name('blogs.')->group(function () {
        Route::get('/', [AdminController::class, 'blogs_index'])->name('index');
        Route::get('/listing', [AdminController::class, 'blogs_listing'])->name('listing');
        Route::get('/create', [AdminController::class, 'blogs_create'])->name('create');
        Route::post('/store', [AdminController::class, 'blogs_store'])->name('store');
        Route::get('/edit/{id}', [AdminController::class, 'blogs_edit'])->name('edit');
        Route::post('/update', [AdminController::class, 'blogs_update'])->name('update');
    });
});



Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');


require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
