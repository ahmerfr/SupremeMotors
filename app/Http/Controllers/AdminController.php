<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Categories;
use App\Models\Products;
use App\Models\ContactForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactFormMail;
use App\Models\Blogs;
use App\Models\Newsletter;
use App\Models\QueryForm;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'own_products' => Products::where('website', 'suprememotors')->count(),
            'total_products' => Products::count(),
            'users' => User::where('role', 'user')->count(),
            'newsletter' => Newsletter::count(),
            'queries' => QueryForm::count(),
            'contacts' => ContactForm::count(),
            'published_blogs' => Blogs::where('publish_status', 'published')->count(),
        ];

        // Customer contact details stay admin-only; editors get an empty feed.
        $recent_queries = auth()->user()->role === 'admin'
            ? QueryForm::latest('created_at')
                ->select('id', 'company', 'contact_name', 'email', 'created_at')
                ->limit(5)
                ->get()
            : collect();

        $recent_products = Products::where('website', 'suprememotors')
            ->latest('created_at')
            ->select('id', 'stock_code', 'title', 'price', 'front_image', 'created_at')
            ->limit(5)
            ->get();

        $by_country = Products::whereNotNull('country')
            ->groupBy('country')
            ->selectRaw('country, COUNT(*) as count')
            ->orderByDesc('count')
            ->get();

        $top_makes = Products::query()
            ->join('categories', 'categories.id', '=', 'products.make_id')
            ->groupBy('categories.id', 'categories.cat_title')
            ->selectRaw('categories.cat_title, COUNT(*) as count')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recent_queries' => $recent_queries,
            'recent_products' => $recent_products,
            'by_country' => $by_country,
            'top_makes' => $top_makes,
        ]);
    }

    public function users_index()
    {
        $users_data = $this->users_listing();
        return Inertia::render('Admin/Users/Index', [
            'users' => $users_data,
        ]);
    }

    public function users_listing()
    {
        $keywords = request()->keywords;
        $role = request()->role;
        $users = User::query()->orderBy('created_at', 'desc');
        if (in_array($role, ['admin', 'editor', 'user'], true)) {
            $users->where('role', $role);
        }
        if ($keywords) {
            $users->where(function ($query) use ($keywords) {
                $query->where('name', 'like', '%' . $keywords . '%')
                    ->orWhere('email', 'like', '%' . $keywords . '%');
            });
        }
        $users = $users->paginate(15)->withQueryString();
        return $users;
    }

    public function users_store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,editor,user',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'email_verified_at' => now(), // admin-created accounts are trusted
        ]);

        return response()->json(['message' => 'User created.', 'user' => $user->only('id', 'name', 'email', 'role')], 201);
    }

    public function users_update_role(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|in:admin,editor,user',
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot change your own role.'], 422);
        }

        $user->role = $request->input('role');
        $user->save();

        return response()->json(['message' => 'Role updated.', 'user' => $user->only('id', 'name', 'role')]);
    }
    public function categories_index(string $type = 'category')
    {
        $categories_data = $this->categories_listing($type);
        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories_data,
            'type' => $type,
        ]);
    }
    public function categories_listing(string $type = 'category')
    {
        $keywords = request()->keywords;
        $categories = Categories::query()
            ->where('type', $type)
            ->withCount([($type === 'make' ? 'make_products' : 'cat_products') . ' as products_count'])
            // Tree order: each top-level category directly followed by its
            // subcategories, so the dash-prefixed children read as a group.
            ->when($type === 'category', fn ($q) => $q->with('parent:id,cat_title')
                ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, cat_title'))
            ->orderBy('created_at', 'desc');
        if ($keywords) {
            $categories->where(function ($query) use ($keywords) {
                $query->where('cat_title', 'like', '%' . $keywords . '%')
                    ->orWhere('description', 'like', '%' . $keywords . '%');
            });
        }
        $categories = $categories->paginate(15)->withQueryString();
        return $categories;
    }

    public function categories_create(string $type = 'category')
    {
        return Inertia::render('Admin/Categories/Create', [
            'type' => $type,
            'parents' => $type === 'category'
                ? Categories::where('type', 'category')->whereNull('parent_id')->orderBy('cat_title')->get(['id', 'cat_title'])
                : [],
        ]);
    }
    public function categories_store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:category,make',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $category = new Categories;
        $category->cat_title = $request->input('title');
        $category->description = "--";
        $category->type = $request->input('type');
        $category->parent_id = $request->input('type') === 'category' ? ($request->input('parent_id') ?: null) : null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('cat_images', 'public');
            $category->image = $imagePath;
        }

        $category->save();

        return response()->json(['message' => 'Category created successfully!'], 201);
    }
    public function categories_edit($id)
    {
        $category = Categories::findOrFail($id);
        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category,
            'parents' => $category->type === 'category'
                ? Categories::where('type', 'category')->whereNull('parent_id')->where('id', '!=', $category->id)
                    ->orderBy('cat_title')->get(['id', 'cat_title'])
                : [],
        ]);
    }
    public function categories_update(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:category,make',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $category = Categories::findOrFail($request->id);
        $category->cat_title = $request->input('title');
        $category->description = "--";
        $category->type = $request->input('type');
        if ($category->type === 'category' && (int) $request->input('parent_id') !== $category->id) {
            $category->parent_id = $request->input('parent_id') ?: null;
        }

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('cat_images', 'public');
            $category->image = $imagePath;
        }
        $category->save();
        return response()->json(['message' => 'Category updated successfully!'], 200);
    }

    public function products_index()
    {
        $products_data = $this->products_listing();
        return Inertia::render('Admin/Products/Index', [
            'products' => $products_data,
        ]);
    }
    public function products_listing()
    {
        $keywords = request()->keywords;
        $products = Products::query()
            ->where(function ($query) {
                $query->where('website', 'suprememotors');
            })
            ->orderBy('created_at', 'desc')
            ->with('category')->with('make');
        if ($keywords) {
            $products->where(function ($query) use ($keywords) {
                $query->where('title', 'like', '%' . $keywords . '%')
                    ->orWhere('description', 'like', '%' . $keywords . '%');
            });
        }
        $products = $products->paginate(15);
        return $products;
    }
    public function products_create()
    {
        $categories = Categories::all();
        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories,
        ]);
    }

    public function products_store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'make_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'country' => 'required|string|max:255',
            'front_image' => 'required|image|mimes:jpg,jpeg,png,gif|max:2048',
            'other_images.*' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'product_details' => 'required|string',
            'model' => 'nullable|string|max:100',
            'model_code' => 'nullable|string|max:60',
            'year' => 'nullable|integer|between:1950,2027',
            'engine_cc' => 'nullable|integer|min:0',
            'mileage_km' => 'nullable|integer|min:0',
            'fuel' => 'nullable|string|max:30',
            'transmission' => 'nullable|string|max:30',
            'condition' => 'nullable|string|max:40',
            'color' => 'nullable|string|max:40',
            'steering' => 'nullable|string|max:10',
            'seats' => 'nullable|integer|between:1,99',
            'doors' => 'nullable|integer|between:1,9',
            'axles' => 'nullable|integer|between:1,9',
            'load_capacity_kg' => 'nullable|integer|min:0',
            'power_hp' => 'nullable|integer|between:10,5000',
            'emission_standard' => 'nullable|string|max:10',
            'running_hours' => 'nullable|integer|min:0',
            'drive_type' => 'nullable|string|max:30',
        ]);

        try {
            $frontImagePath = $request->file('front_image')->store('product_images', 'public');
            $otherImagePaths = [];
            if ($request->has('other_images')) {
                foreach ($request->file('other_images') as $image) {
                    $otherImagePaths[] = $image->store('product_images', 'public');
                }
            }
            $attributeFields = [
                'model', 'model_code', 'year', 'engine_cc', 'mileage_km', 'fuel',
                'transmission', 'condition', 'color', 'steering', 'seats', 'doors', 'drive_type',
                'axles', 'load_capacity_kg', 'power_hp', 'emission_standard', 'running_hours',
            ];
            $product = Products::create(array_merge([
                'title' => $validatedData['title'],
                'category_id' => $validatedData['category_id'],
                'make_id' => $validatedData['make_id'],
                'price' => $validatedData['price'],
                'website' => 'suprememotors',
                'country' => $validatedData['country'],
                'front_image' => $frontImagePath,
                'other_images' => $otherImagePaths,
                'product_details' => $validatedData['product_details'],
            ], collect($validatedData)->only($attributeFields)->all()));

            $product->stock_code = 'SM'.$product->id;
            $product->save();
            return response()->json([
                'message' => 'Product created successfully.',
                'product' => $product,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error occurred while creating product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function products_edit($id)
    {
        $product = Products::findOrFail($id);
        $categories = Categories::all();
        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }
    public function products_update(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|exists:products,id',
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'make_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'country' => 'required|string|max:255',
            'front_image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'other_images.*' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'product_details' => 'required|string',
            'removed_images' => 'nullable|string',
            'model' => 'nullable|string|max:100',
            'model_code' => 'nullable|string|max:60',
            'year' => 'nullable|integer|between:1950,2027',
            'engine_cc' => 'nullable|integer|min:0',
            'mileage_km' => 'nullable|integer|min:0',
            'fuel' => 'nullable|string|max:30',
            'transmission' => 'nullable|string|max:30',
            'condition' => 'nullable|string|max:40',
            'color' => 'nullable|string|max:40',
            'steering' => 'nullable|string|max:10',
            'seats' => 'nullable|integer|between:1,99',
            'doors' => 'nullable|integer|between:1,9',
            'axles' => 'nullable|integer|between:1,9',
            'load_capacity_kg' => 'nullable|integer|min:0',
            'power_hp' => 'nullable|integer|between:10,5000',
            'emission_standard' => 'nullable|string|max:10',
            'running_hours' => 'nullable|integer|min:0',
            'drive_type' => 'nullable|string|max:30',
        ]);

        try {
            $product = Products::findOrFail($validatedData['id']);

            $product->title = $validatedData['title'];
            $product->category_id = $validatedData['category_id'];
            $product->make_id = $validatedData['make_id'];
            $product->price = $validatedData['price'];
            $product->country = $validatedData['country'];
            $product->product_details = $validatedData['product_details'];

            foreach ([
                'model', 'model_code', 'year', 'engine_cc', 'mileage_km', 'fuel',
                'transmission', 'condition', 'color', 'steering', 'seats', 'doors', 'drive_type',
                'axles', 'load_capacity_kg', 'power_hp', 'emission_standard', 'running_hours',
            ] as $field) {
                $product->{$field} = $validatedData[$field] ?? null;
            }
            if (! $product->stock_code) {
                $product->stock_code = 'SM'.$product->id;
            }

            if ($request->hasFile('front_image')) {
                if ($product->front_image && Storage::disk('public')->exists($product->front_image)) {
                    Storage::disk('public')->delete($product->front_image);
                }
                $frontImagePath = $request->file('front_image')->store('product_images', 'public');
                $product->front_image = $frontImagePath;
            }

            $currentOtherImages = $product->other_images ? $product->other_images : [];

            if ($request->has('removed_images') && !empty($request->input('removed_images'))) {
                $removedImages = json_decode($request->input('removed_images'), true);
                foreach ($removedImages as $removedImage) {
                    // Remove from current images array
                    $index = array_search($removedImage, $currentOtherImages);
                    if ($index !== false) {
                        unset($currentOtherImages[$index]);
                        // Delete the file from storage
                        if (Storage::disk('public')->exists($removedImage)) {
                            Storage::disk('public')->delete($removedImage);
                        }
                    }
                }
                $currentOtherImages = array_values($currentOtherImages);
            }
            if ($request->has('other_images')) {
                foreach ($request->file('other_images') as $image) {
                    $otherImagePath = $image->store('product_images', 'public');
                    $currentOtherImages[] = $otherImagePath;
                }
            }

            $product->other_images = !empty($currentOtherImages) ? $currentOtherImages : null;
            $product->save();

            return response()->json([
                'message' => 'Product updated successfully.',
                'product' => $product,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error occurred while updating product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function newsletter_index()
    {
        $newsletter = $this->newsletter_listing();
        return Inertia::render('Admin/Newsletter/Index', [
            'newsletter' => $newsletter,
        ]);
    }

    public function newsletter_listing()
    {
        $keywords = request()->keywords;
        $newsletter = Newsletter::query()->orderBy('created_at', 'desc');
        if ($keywords) {
            $newsletter->where(function ($query) use ($keywords) {
                $query->where('email', 'like', '%' . $keywords . '%');
            });
        }
        $newsletter = $newsletter->paginate(15);
        return $newsletter;
    }


    public function query_form_index()
    {
        $query_form = $this->query_form_listing();
        return Inertia::render('Admin/QueryForm/Index', [
            'query_form' => $query_form,
        ]);
    }

    public function query_form_listing()
    {
        $keywords = request()->keywords;
        $query_form = QueryForm::query()->orderBy('created_at', 'desc');
        if ($keywords) {
            $query_form->where(function ($query) use ($keywords) {
                $query->where('email', 'like', '%' . $keywords . '%');
            });
        }
        $query_form = $query_form->paginate(15);
        return $query_form;
    }

    public function query_form_view($id)
    {
        $query_form = QueryForm::findOrFail($id);
        return Inertia::render('Admin/QueryForm/View', [
            'query' => $query_form,
        ]);
    }

    public function blogs_index()
    {
        $blogs_data = $this->blogs_listing();
        return Inertia::render('Admin/Blogs/Index', [
            'blogs' => $blogs_data,
        ]);
    }

    public function blogs_listing()
    {
        $keywords = request()->keywords;

        $blogs = Blogs::query()->orderBy('created_at', 'desc');

        if ($keywords) {
            $blogs->where(function ($query) use ($keywords) {
                $query->where('title', 'like', '%' . $keywords . '%')
                    ->orWhere('short_description', 'like', '%' . $keywords . '%')
                    ->orWhere('content', 'like', '%' . $keywords . '%');
            });
        }

        return $blogs->paginate(15);
    }


    public function blogs_create()
    {
        return Inertia::render('Admin/Blogs/Create');
    }

    public function blogs_store(Request $request)
    {
        $request->validate([
            'title'             => 'required|string|max:100',
            'short_description' => 'required|string|max:250',
            'content'           => 'required|string',
            'cover_image'       => 'required|image|mimes:jpg,jpeg,png|max:4096',
            'category'          => 'required|string',
            'tags'              => 'required|array',
            'publish_status'    => 'required|in:draft,published',

            // SEO
            'meta_title'        => 'nullable|string|max:255',
            'meta_description'  => 'nullable|string|max:500',
            'meta_keywords'     => 'nullable|string|max:255',
        ]);

        $slug = Str::slug($request->title);
        $count = Blogs::where('slug', 'like', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }
        $coverImagePath = $request->file('cover_image')->store('blog_images', 'public');
        Blogs::create([
            'title'             => $request->title,
            'slug'              => $slug,
            'short_description' => $request->short_description,
            'content'           => $request->content,
            'cover_image'       => $coverImagePath,
            'category'          => $request->category,
            'tags'              => $request->tags ?? [],
            'publish_status'    => $request->publish_status,

            // SEO fields
            'meta_title'        => $request->meta_title ?? $request->title,
            'meta_description'  => $request->meta_description ?? $request->short_description,
            'meta_keywords'     => $request->meta_keywords,
        ]);

        return redirect()->back()->with('success', 'Blog created successfully!');
    }

    public function blogs_edit($id)
    {
        $blogs = Blogs::findOrFail($id);
        return Inertia::render('Admin/Blogs/Edit', [
            'blog' => $blogs,
        ]);
    }

    public function blogs_update(Request $request)
    {
        $blog = Blogs::findOrFail($request->id);
        $request->validate([
            'title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'content' => 'required|string',
            'cover_image' => $request->cover_image ? 'nullable' : 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'category' => 'nullable|string',
            'tags' => 'nullable|array',
            'publish_status' => 'required|in:draft,published',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:255',
        ]);

        // Update fields
        $blog->title = $request->title;
        $blog->short_description = $request->short_description;
        $blog->content = $request->content;
        $blog->category = $request->category;
        $blog->tags = $request->tags ?? [];
        $blog->publish_status = $request->publish_status;
        $blog->meta_title = $request->meta_title;
        $blog->meta_description = $request->meta_description;
        $blog->meta_keywords = $request->meta_keywords;

        if ($request->hasFile('cover_image')) {
            if ($blog->cover_image && Storage::disk('public')->exists($blog->cover_image)) {
                Storage::disk('public')->delete($blog->cover_image);
            }

            // Store new image
            $path = $request->file('cover_image')->store('blog_covers', 'public');
            $blog->cover_image = $path;
        }

        // Save blog
        $blog->save();

        return redirect()->route('admin.blogs.index')->with('success', 'Blog updated successfully.');
    }
}
