<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use App\Models\Blogs;
use App\Models\Categories;
use App\Models\ContactForm;
use App\Models\Newsletter;
use App\Models\Products;
use App\Models\QueryForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

// use Spatie\Sitemap\SitemapGenerator;

class DashboardController extends Controller
{
    public function home()
    {
        // Home.vue consumes: categories/makes with `products_count`, and
        // featured_products_{china,japan,thailand} cards that use the
        // category relation, website and price.
        $data = Cache::remember('home_page_data', 60, function () {
            $makeCounts = Products::query()
                ->whereNotNull('make_id')
                ->groupBy('make_id')
                ->selectRaw('make_id, COUNT(*) as count')
                ->pluck('count', 'make_id');

            $makes = Categories::where('type', 'make')
                ->select('id', 'cat_title', 'image')
                ->get()
                ->map(fn ($make) => [
                    'id'             => $make->id,
                    'cat_title'      => $make->cat_title,
                    'image'          => $make->image,
                    'products_count' => $makeCounts[$make->id] ?? 0,
                ]);

            // Only the 7 top-level categories, counts rolled up from their
            // subcategories.
            $categoryCounts = Products::query()
                ->whereNotNull('category_id')
                ->groupBy('category_id')
                ->selectRaw('category_id, COUNT(*) as c')
                ->pluck('c', 'category_id');

            $allCategories = Categories::where('type', 'category')->get(['id', 'cat_title', 'image', 'parent_id']);

            $categories = $allCategories->whereNull('parent_id')
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'cat_title' => $p->cat_title,
                    'image' => $p->image,
                    'products_count' => ($categoryCounts[$p->id] ?? 0)
                        + $allCategories->where('parent_id', $p->id)->sum(fn ($c) => $categoryCounts[$c->id] ?? 0),
                ])
                ->filter(fn ($p) => $p['products_count'] > 0)
                ->sortByDesc('products_count')
                ->values();

            // Extra rows beyond the 6 shown: many scraped image URLs are dead
            // (delisted vehicles), so the frontend drops cards whose image
            // fails to load and backfills from these candidates.
            $featured = fn (string $country) => Products::with(['category:id,cat_title', 'make:id,cat_title'])
                ->where('country', $country)
                ->whereNotNull('front_image')
                ->whereNull('front_image_dead_at')
                ->select('id', 'title', 'front_image', 'price', 'website', 'country', 'category_id', 'make_id',
                    'fuel', 'transmission', 'mileage_km', 'year')
                ->latest('created_at')
                ->limit(24)
                ->get();

            $body_types = Products::whereNotNull('body_style')
                ->groupBy('body_style')
                ->selectRaw('body_style, COUNT(*) as count')
                ->orderByDesc('count')
                ->get();

            return [
                'categories' => $categories,
                'makes' => $makes,
                'body_types' => $body_types,
                'featured_products_china' => $featured('China'),
                'featured_products_japan' => $featured('Japan'),
                'featured_products_europe' => $featured('Europe'),
            ];
        });

        return Inertia::render('Home', $data);
    }


    public function body_type_products()
    {
        $style = (string) request()->query('style', '');

        // Slim payload for the homepage tiles: no pagination count, no
        // product_details blob — the full listing endpoint sends ~100KB.
        // Plain newest-first returns a single-make wall (the newest scrape
        // region is Toyota-dominated), so interleave the newest rows of each
        // make instead: every make's freshest card first, then seconds, etc.
        return Cache::remember('home_bt_' . md5($style), 60, function () use ($style) {
            $ids = collect(\DB::select(
                'SELECT id FROM (
                    SELECT id, created_at,
                           ROW_NUMBER() OVER (PARTITION BY make_id ORDER BY created_at DESC) AS rn
                    FROM products
                    WHERE body_style = ? AND front_image IS NOT NULL AND front_image_dead_at IS NULL
                ) t WHERE rn <= 6 ORDER BY rn ASC, created_at DESC LIMIT 36',
                [$style]
            ))->pluck('id');

            return Products::with(['category:id,cat_title', 'make:id,cat_title'])
                ->whereIn('id', $ids)
                ->select('id', 'title', 'front_image', 'price', 'website', 'country',
                    'category_id', 'make_id', 'fuel', 'transmission', 'mileage_km')
                ->get()
                ->sortBy(fn ($p) => $ids->search($p->id))
                ->values();
        });
    }

    public function filter_bodystyle()
    {
        $body_styles = request()->body_style;
        $products = Products::where("body_style", $body_styles)->select("title", "front_image", "id", "product_details", "body_style")->limit(8)->get();
        return response()->json([
            'success' => true,
            'body_styles_products' => $products,
        ], 200);
    }

    public function filter_countryproducts()
    {
        $country = request()->country;
        $products = Products::where("country", $country)->select("title", "front_image", "id", "product_details", "country")->limit(8)->get();
        return response()->json([
            'success' => true,
            'country_products' => $products,
        ], 200);
    }

    public function contact_page()
    {
        return Inertia::render('ContactUs');
    }

    public function contact_save(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'consent' => 'required|accepted',
        ]);

        try {
            $contact = new ContactForm;
            $contact->name = $request->name;
            $contact->email = $request->email;
            $contact->phone = $request->phone;
            $contact->subject = $request->subject;
            $contact->message = $request->message;
            $contact->consent = $request->consent;
            $contact->save();

            Mail::to('info@suprememotors.ltd')->send(new ContactFormMail($validated));

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your message! We\'ll get back to you shortly.',
            ]);
        } catch (\Exception $e) {
            // \Log::error('Contact form submission error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'There was an error processing your request. Please try again later.',
            ], 500);
        }
    }

    public function about_page()
    {
        return Inertia::render('AboutUs');
    }

    public function customerreview_page()
    {
        return Inertia::render('CustomerReview');
    }

    public function termscondition_page()
    {
        return Inertia::render('TermsCondition');
    }


    public function faqs_page()
    {
        return Inertia::render('FAQs');
    }

    public function customer_support_page()
    {
        return Inertia::render('CustomerSupport');
    }

    public function queryform_page()
    {
        return Inertia::render('QueryForm');
    }

    public function queryform_submit(Request $request)
    {
        $validated = $request->validate([
            'company'       => 'required',
            'contact_name'  => 'required',
            'email'         => 'required|email',
            'phone'         => 'required',
            'meeting'       => 'required',
            'visit'         => 'required',
            'closing'       => 'required|numeric',
            'message'       => 'required',
        ]);

        $query = new QueryForm;
        $query->company = $validated['company'];
        $query->contact_name = $validated['contact_name'];
        $query->email = $validated['email'];
        $query->phone = $validated['phone'];
        $query->meeting = $validated['meeting'];
        $query->visit = $validated['visit'];
        $query->closing = $validated['closing'];
        $query->message = $validated['message'];
        $query->save();

        return response()->json([
            'success' => true,
            'message' => 'Your query has been submitted successfully!'
        ], 200);
    }


    public function bankdetails_page()
    {
        return Inertia::render('BankDetails');
    }

    public function howtobuy_page()
    {
        return Inertia::render('HowtoBuy');
    }

    public function newsletter_subscribe(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        $existing = Newsletter::where('email', $request->email)->first();
        if ($existing) {
            return response()->json([
                'message' => 'This email is already subscribed.'
            ], 422);
        }
        $newsletter = new Newsletter;
        $newsletter->email = $request->email;
        $newsletter->save();
        return response()->json([
            'message' => 'Successfully subscribed!'
        ], 200);
    }
    public function product_category($category_id)
    {
        $category = Categories::select('id', 'cat_title', 'image', 'type')
            ->findOrFail($category_id);

        // A top-level category page covers its subcategories' products too.
        $catIds = Categories::expandWithChildren([$category->id]);

        // Per-make counts within this category — covered by products_category_make_idx.
        $productCounts = Products::query()
            ->whereIn('category_id', $catIds)
            ->whereNotNull('make_id')
            ->groupBy('make_id')
            ->selectRaw('make_id, COUNT(*) as count')
            ->pluck('count', 'make_id');

        $totalProductsCount = Products::whereIn('category_id', $catIds)->count();

        $makes = Categories::where('type', 'make')
            ->whereIn('id', $productCounts->keys())
            ->select('id', 'cat_title', 'image')
            ->get()
            ->map(fn ($make) => [
                'category_id'   => (string) $make->id,
                'cat_title'     => $make->cat_title,
                'image'         => $make->image,
                'product_count' => $productCounts[$make->id] ?? 0,
            ]);

        $bodyStyle = Products::query()
            ->whereIn('category_id', $catIds)
            ->whereNotNull('body_style')
            ->groupBy('body_style')
            ->selectRaw('body_style')
            ->get();

        $country_products = Products::where("country", "China")
            ->whereIn("category_id", $catIds)
            ->select("title", "front_image", "id", "product_details", "body_style")
            ->limit(8)
            ->get();

        return Inertia::render('ProductCategory', [
            'category'             => $category,
            'makes'                => $makes,
            'body_styles'          => $bodyStyle,
            'total_products_count' => $totalProductsCount,
            'country_products'     => $country_products,
        ]);
    }
    public function filter_countryproduct_category()
    {
        $category_id = request()->category_id;
        $country = request()->country;
        $products = Products::where("country", $country)
            ->whereIn("category_id", Categories::expandWithChildren([(int) $category_id]))
            ->select("title", "front_image", "id", "product_details", "country")->limit(8)->get();
        return response()->json([
            'success' => true,
            'country_products' => $products,
        ], 200);
    }

    public function blogs_page()
    {
        $blogs = Blogs::orderBy("created_at", "DESC")->where("publish_status", "published")->paginate(9);
        return Inertia::render('Blogs', [
            'blogs'     => $blogs,
        ]);
    }

    public function blog_detail($slug)
    {
        $blog = Blogs::where('slug', $slug)->where('publish_status', 'published')->firstOrFail();
        return Inertia::render('BlogDetails', [
            'blog' => $blog,
        ]);
    }
}
