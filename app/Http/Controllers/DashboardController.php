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
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

// use Spatie\Sitemap\SitemapGenerator;

class DashboardController extends Controller
{
    public function home()
    {
        $productCounts = Products::query()
            ->whereNotNull('make_id')
            ->groupBy('make_id')
            ->selectRaw('make_id, COUNT(*) as count')
            ->pluck('count', 'make_id');

        $makes = Categories::where('type', 'make')
            ->select('id', 'cat_title', 'image')
            ->get()
            ->map(fn ($make) => [
                'category_id'   => (string) $make->id,
                'cat_title'     => $make->cat_title,
                'image'         => $make->image,
                'product_count' => $productCounts[$make->id] ?? 0,
            ]);

        $bodyStyle = Products::query()
            ->whereNotNull('body_style')
            ->groupBy('body_style')
            ->selectRaw('body_style, COUNT(*) as count')
            ->get();

        $products = Products::where("body_style", "Sedan")
            ->select("title", "front_image", "id", "product_details", "body_style")
            ->limit(8)
            ->get();

        $country_products = Products::where("country", "China")
            ->select("title", "front_image", "id", "product_details", "body_style")
            ->limit(8)
            ->get();

        $blogs = Blogs::orderBy("created_at", "DESC")->where("publish_status", "published")->limit(3)->get();

        return Inertia::render('Home', [
            'body_styles' => $bodyStyle,
            'body_styles_products' => $products,
            'country_products' => $country_products,
            'makes' => $makes,
            'blogs' => $blogs
        ]);
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
