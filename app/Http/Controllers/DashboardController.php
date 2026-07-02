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

            $categories = Categories::query()
                ->join('products', 'products.category_id', '=', 'categories.id')
                ->where('categories.type', 'category')
                ->groupBy('categories.id', 'categories.cat_title', 'categories.image', 'categories.created_at')
                ->orderByDesc('categories.created_at')
                ->selectRaw('categories.id, categories.cat_title, categories.image, COUNT(products.id) as products_count')
                ->get();

            $featured = fn (string $country) => Products::with(['category', 'make'])
                ->where('country', $country)
                ->whereNotNull('front_image')
                ->select('id', 'title', 'front_image', 'price', 'website', 'country', 'category_id', 'make_id',
                    'fuel', 'transmission', 'mileage_km', 'year', 'product_details')
                ->latest('created_at')
                ->limit(8)
                ->get();

            return [
                'categories' => $categories,
                'makes' => $makes,
                'featured_products_china' => $featured('China'),
                'featured_products_japan' => $featured('Japan'),
                'featured_products_thailand' => $featured('Thailand'),
            ];
        });

        return Inertia::render('Home', $data);
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

        // Per-make counts within this category — covered by products_category_make_idx.
        $productCounts = Products::query()
            ->where('category_id', $category->id)
            ->whereNotNull('make_id')
            ->groupBy('make_id')
            ->selectRaw('make_id, COUNT(*) as count')
            ->pluck('count', 'make_id');

        $totalProductsCount = Products::where('category_id', $category->id)->count();

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
            ->where('category_id', $category->id)
            ->whereNotNull('body_style')
            ->groupBy('body_style')
            ->selectRaw('body_style')
            ->get();

        $country_products = Products::where("country", "China")
            ->where("category_id", $category->id)
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
        $products = Products::where("country", $country)->where("category_id", $category_id)
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
