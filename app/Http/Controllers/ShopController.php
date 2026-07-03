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
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class ShopController extends Controller
{
    public function home()
    {
        $data = Cache::remember('shop_home_data', 1800, function () {
            // Filter sidebar shows the 7 top-level categories; each count
            // rolls up the category's own products plus its subcategories'.
            $counts = Products::query()
                ->whereNotNull('category_id')
                ->groupBy('category_id')
                ->selectRaw('category_id, COUNT(*) as c')
                ->pluck('c', 'category_id');

            $all = Categories::where('type', 'category')->get(['id', 'cat_title', 'image', 'parent_id']);

            $categories = $all->whereNull('parent_id')
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'cat_title' => $p->cat_title,
                    'image' => $p->image,
                    'products_count' => ($counts[$p->id] ?? 0)
                        + $all->where('parent_id', $p->id)->sum(fn ($c) => $counts[$c->id] ?? 0),
                ])
                ->filter(fn ($p) => $p['products_count'] > 0)
                ->sortByDesc('products_count')
                ->values()
                ->toArray();

            $makes = Categories::where('type', 'make')
                ->orderBy('created_at', 'desc')
                ->select('id', 'cat_title', 'image')
                ->get()
                ->toArray();

            return [
                'categories' => $categories,
                'makes' => $makes,
            ];
        });

        $products = $this->listing();

        return Inertia::render('Shop', [
            'products' => $products,
            'categories' => $data['categories'],
            'makes' => $data['makes'],
        ]);
    }

    public function listing()
    {
        $request = request();
        $query = Products::with(['category:id,cat_title', 'make:id,cat_title']);

        $type = $request->input('type') ?? null;

        if ($type == 'search') {
            // Get all request data
            $filter_data = $request->all();

            // Basic filters
            $search = $filter_data['search'] ?? null;
            $makeId = $filter_data['make'] ?? null;
            $bodyStyle = $filter_data['body_style'] ?? null;

            // Apply search filter
            if ($search) {
                $query->search($search);
            }

            // Apply make filter
            if ($makeId) {
                $query->where('make_id', $makeId);
            }

            // Apply body style filter
            if ($bodyStyle) {
                $query->where('body_style', $bodyStyle);
            }

            // Apply price range filter if applicable
            $priceMin = $filter_data['price_min'] ?? null;
            $priceMax = $filter_data['price_max'] ?? null;

            if ($priceMin && $priceMax) {
                $query->whereBetween('price', [(int)$priceMin, (int)$priceMax]);
            } elseif ($priceMin) {
                $query->where('price', '>=', (int)$priceMin);
            } elseif ($priceMax) {
                $query->where('price', '<=', (int)$priceMax);
            }

            $this->applyAttributeFilters($query, $filter_data);
        } else {
            // Original approach for non-search type requests
            $categoryFilters = $request->filled('category') ? explode(',', $request->input('category')) : [];
            $makeFilters = $request->filled('make') ? explode(',', $request->input('make')) : [];
            $countryFilters = $request->filled('country') ? explode(',', $request->input('country')) : [];
            $priceFilter = $request->input('price');
            $bodyStyle = $request->input('body_style');
            $search = $request->input('search');

            // A top-level category also matches products filed under its subcategories.
            if (!empty($categoryFilters)) {
                $categoryFilters = Categories::expandWithChildren($categoryFilters);
            }

            $query = $query->when($search, fn ($q) => $q->search($search))
                ->when(!empty($categoryFilters), fn($q) => $q->whereIn('category_id', $categoryFilters))
                ->when(!empty($makeFilters), fn($q) => $q->whereIn('make_id', $makeFilters))
                ->when($bodyStyle, fn($q) => $q->where('body_style', $bodyStyle))
                ->when(!empty($countryFilters), fn($q) => $q->whereIn('country', $countryFilters))
                ->when($priceFilter, function ($q) use ($priceFilter) {
                    switch ($priceFilter) {
                        case 'under-500':
                            $q->where('price', '<', 500);
                            break;
                        case '500-1000':
                            $q->whereBetween('price', [500, 1000]);
                            break;
                        case '1000-2000':
                            $q->whereBetween('price', [1000, 2000]);
                            break;
                        case '2000-5000':
                            $q->whereBetween('price', [2000, 5000]);
                            break;
                        case '5000-10000':
                            $q->whereBetween('price', [5000, 10000]);
                            break;
                        case '10000-20000':
                            $q->whereBetween('price', [10000, 20000]);
                            break;
                        case 'over-20000':
                            $q->where('price', '>', 20000);
                            break;
                    }
                });

            $this->applyAttributeFilters($query, $request->all());
        }
        // paginate()'s COUNT(*) costs ~100-200ms on 453K rows even warm;
        // totals per filter signature barely change, so cache them briefly.
        $query->orderByDesc('created_at');
        $page = max(1, (int) $request->input('page', 1));
        $countKey = 'listing_count_' . md5(json_encode(collect($request->except('page'))->sortKeys()->all()));
        $total = Cache::remember($countKey, 300, fn () => $query->toBase()->getCountForPagination());

        $results = new \Illuminate\Pagination\LengthAwarePaginator(
            $query->forPage($page, 30)->get(),
            $total,
            30,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Cards render only a ~180-char snippet of product_details, but the
        // full HTML blob dominated the JSON payload (~130KB/page). Ship the
        // snippet instead; the product-detail endpoint still serves the full blob.
        $results->getCollection()->transform(function ($p) {
            $p->product_details = \Illuminate\Support\Str::limit(trim(strip_tags($p->product_details ?? '')), 220);
            return $p;
        });

        return $results;
    }

    /**
     * Structured-attribute filters, available to both listing branches.
     * Ranges: year_from/year_to, mileage_min/mileage_max, engine_min/engine_max.
     * Lists (comma-separated): fuel, transmission, condition, steering, drive_type.
     * Exact: seats.
     */
    private function applyAttributeFilters($query, array $data): void
    {
        $range = function (string $column, ?string $min, ?string $max) use ($query) {
            if ($min !== null && $min !== '' && $max !== null && $max !== '') {
                $query->whereBetween($column, [min((int) $min, (int) $max), max((int) $min, (int) $max)]);
            } elseif ($min !== null && $min !== '') {
                $query->where($column, '>=', (int) $min);
            } elseif ($max !== null && $max !== '') {
                $query->where($column, '<=', (int) $max);
            }
        };

        $range('year', $data['year_from'] ?? null, $data['year_to'] ?? null);
        $range('mileage_km', $data['mileage_min'] ?? null, $data['mileage_max'] ?? null);
        $range('engine_cc', $data['engine_min'] ?? null, $data['engine_max'] ?? null);
        $range('power_hp', $data['power_min'] ?? null, $data['power_max'] ?? null);
        $range('load_capacity_kg', $data['load_min'] ?? null, $data['load_max'] ?? null);
        $range('running_hours', $data['hours_min'] ?? null, $data['hours_max'] ?? null);

        foreach (['fuel', 'transmission', 'condition', 'steering', 'drive_type', 'emission_standard'] as $column) {
            $value = $data[$column] ?? null;
            if ($value !== null && $value !== '') {
                $query->whereIn($column, array_map('trim', explode(',', $value)));
            }
        }

        if (! empty($data['seats'])) {
            $query->where('seats', (int) $data['seats']);
        }

        if (! empty($data['doors'])) {
            $query->where('doors', (int) $data['doors']);
        }

        if (! empty($data['axles'])) {
            $query->where('axles', (int) $data['axles']);
        }
    }

    public function product_detail($id)
    {
        $product_detail = Products::with('category')
            ->when(
                ctype_digit((string) $id),
                fn ($q) => $q->where('id', $id),
                fn ($q) => $q->where('mongo_id', $id) // legacy Mongo URLs
            )
            ->first();

        if (!$product_detail) {
            abort(404);
        }

        // Default to China for similar products
        $similar_products = $this->randomSimilarProducts($product_detail->category_id, 'China', $product_detail->id);

        return Inertia::render('ProductDetail', [
            "product_detail" => $product_detail,
            "similar_products" => $similar_products,
        ]);
    }

    public function filter_country_products(Request $request)
    {
        $country = $request->input('country', 'China');
        $product_id = $request->input('product_id');

        $product = Products::find($product_id);

        if (!$product) {
            return response()->json(['similar_products' => []]);
        }

        return response()->json([
            'similar_products' => $this->randomSimilarProducts($product->category_id, $country, $product->id),
        ]);
    }

    /**
     * Random N products in the same category+country. Two-step: pick ids via
     * the (category_id, country) covering index, then hydrate — avoids
     * ORDER BY RAND() over full rows.
     */
    private function randomSimilarProducts(?int $categoryId, string $country, int $excludeId, int $limit = 4)
    {
        if ($categoryId === null) {
            return collect();
        }

        $randomIds = Products::query()
            ->where('category_id', $categoryId)
            ->where('country', $country)
            ->where('id', '!=', $excludeId)
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('id');

        return Products::whereIn('id', $randomIds)->with('category')->get();
    }

    public function search_products(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Products::query()
            ->with('make:id,cat_title')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('country', 'like', "%{$query}%");
            })
            ->select('id', 'title', 'front_image', 'make_id', 'country', 'price')
            ->limit(10)
            ->get();

        $results = $products->map(fn ($product) => [
            'id' => $product->id,
            'title' => $product->title ?? '',
            'front_image' => $product->front_image ?? '',
            'make' => $product->make ? ['cat_title' => $product->make->cat_title] : null,
            'country' => $product->country ?? '',
            'price' => $product->price ?? 0,
        ])->values();

        return response()->json($results);
    }
}
