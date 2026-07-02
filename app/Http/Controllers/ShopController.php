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
        $data = Cache::remember('shop_home_data', 60, function () {
            // Inner join drops categories with zero products (parity with the
            // old Mongo pipeline's `products != []` stage).
            $categories = Categories::query()
                ->join('products', 'products.category_id', '=', 'categories.id')
                ->where('categories.type', 'category')
                ->groupBy('categories.id', 'categories.cat_title', 'categories.image', 'categories.created_at')
                ->orderByDesc('categories.created_at')
                ->selectRaw('categories.id, categories.cat_title, categories.image, COUNT(products.id) as products_count')
                ->get()
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
        $query = Products::with(['category', 'make']);

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

            $yearFrom = $filter_data['year_from'] ?? null;
            $yearTo = $filter_data['year_to'] ?? null;

            if ($yearFrom || $yearTo) {
                $yearPatterns = [];

                if ($yearFrom && $yearTo) {
                    $minYear = min((int)$yearFrom, (int)$yearTo);
                    $maxYear = max((int)$yearFrom, (int)$yearTo);
                    $yearPatterns = range($minYear, $maxYear);
                } elseif ($yearFrom) {
                    $yearPatterns = range((int)$yearFrom, (int)$yearFrom + 4);
                } elseif ($yearTo) {
                    $yearPatterns = range((int)$yearTo - 4, (int)$yearTo);
                }

                if (!empty($yearPatterns)) {
                    $yearRegex = implode('|', $yearPatterns);
                    // Match just the year numbers in product_details
                    $query->whereRaw('product_details REGEXP ?', ["\\b({$yearRegex})\\b"]);
                }
            }

            // Mileage: check the mileage section exists in the document
            $mileageMin = $filter_data['mileage_min'] ?? null;
            $mileageMax = $filter_data['mileage_max'] ?? null;

            if ($mileageMin || $mileageMax) {
                $query->where('product_details', 'like', '%<strong>Mileage:</strong>%');
            }
        } else {
            // Original approach for non-search type requests
            $categoryFilters = $request->filled('category') ? explode(',', $request->input('category')) : [];
            $makeFilters = $request->filled('make') ? explode(',', $request->input('make')) : [];
            $countryFilters = $request->filled('country') ? explode(',', $request->input('country')) : [];
            $priceFilter = $request->input('price');
            $bodyStyle = $request->input('body_style');
            $search = $request->input('search');

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
        }
        $results = $query->orderByDesc('created_at')->paginate(30);

        return $results;
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
