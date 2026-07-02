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
            $categories = Categories::raw(function ($collection) {
                return $collection->aggregate([
                    ['$match' => ['type' => 'category']],
                    [
                        '$addFields' => [
                            'id_as_string' => ['$toString' => '$_id']
                        ]
                    ],
                    [
                        '$lookup' => [
                            'from' => 'products',
                            'let' => [
                                'categoryId' => '$_id',
                                'categoryIdString' => '$id_as_string'
                            ],
                            'pipeline' => [
                                [
                                    '$match' => [
                                        '$expr' => [
                                            '$or' => [
                                                ['$eq' => ['$category_id', '$$categoryId']],
                                                ['$eq' => ['$category_id', '$$categoryIdString']]
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    '$project' => ['_id' => 1]
                                ],
                                ['$limit' => 1]
                            ],
                            'as' => 'products'
                        ]
                    ],
                    [
                        '$match' => ['products' => ['$ne' => []]]
                    ],
                    [
                        '$lookup' => [
                            'from' => 'products',
                            'let' => [
                                'categoryId' => '$_id',
                                'categoryIdString' => '$id_as_string'
                            ],
                            'pipeline' => [
                                [
                                    '$match' => [
                                        '$expr' => [
                                            '$or' => [
                                                ['$eq' => ['$category_id', '$$categoryId']],
                                                ['$eq' => ['$category_id', '$$categoryIdString']]
                                            ]
                                        ]
                                    ]
                                ],
                                ['$count' => 'count']
                            ],
                            'as' => 'products_count'
                        ]
                    ],
                    [
                        '$project' => [
                            '_id' => 1,
                            'cat_title' => 1,
                            'image' => 1,
                            'products_count' => ['$ifNull' => [['$arrayElemAt' => ['$products_count.count', 0]], 0]],
                            'created_at' => 1
                        ]
                    ],
                    ['$sort' => ['created_at' => -1]]
                ]);
            });

            $makes = Categories::where('type', 'make')
                ->orderBy('created_at', 'desc')
                ->select('_id', 'cat_title', 'image')
                ->get()
                ->toArray();

            return [
                'categories' => collect($categories)->toArray(),
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
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'regex', "/$search/i")
                        ->orWhere('product_details', 'regex', "/$search/i");
                });
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
                    $minYear = (int)$yearFrom;
                    $maxYear = (int)$yearTo;

                    if ($minYear > $maxYear) {
                        $temp = $minYear;
                        $minYear = $maxYear;
                        $maxYear = $temp;
                    }

                    for ($year = $minYear; $year <= $maxYear; $year++) {
                        $yearPatterns[] = $year;
                    }
                } elseif ($yearFrom) {
                    $minYear = (int)$yearFrom;
                    $yearPatterns = range($minYear, $minYear + 4);
                } elseif ($yearTo) {
                    $maxYear = (int)$yearTo;
                    $yearPatterns = range($maxYear - 4, $maxYear);
                }

                if (!empty($yearPatterns)) {
                    $yearRegex = implode('|', $yearPatterns);
                    // Match just the year numbers in product_details, not the full HTML structure
                    $query->where('product_details', 'regex', "/\b($yearRegex)\b/");
                }
            }


            // For mileage, we'll do basic filtering in the database and refinement in PHP
            // We're just checking if the mileage section exists in the document
            $mileageMin = $filter_data['mileage_min'] ?? null;
            $mileageMax = $filter_data['mileage_max'] ?? null;

            if ($mileageMin || $mileageMax) {
                $query->where('product_details', 'regex', "/<strong>Mileage:<\/strong>/i");
            }
        } else {
            // Original approach for non-search type requests
            $categoryFilters = $request->filled('category') ? explode(',', $request->input('category')) : [];
            $makeFilters = $request->filled('make') ? explode(',', $request->input('make')) : [];
            $countryFilters = $request->filled('country') ? explode(',', $request->input('country')) : [];
            $priceFilter = $request->input('price');
            $bodyStyle = $request->input('body_style');
            $search = $request->input('search');

            $query = $query->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'regex', "/$search/i")
                        ->orWhere('product_details', 'regex', "/$search/i");
                });
            })
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
        $product_detail = Products::where('_id', $id)->with('category')->first();

        if (!$product_detail) {
            abort(404);
        }

        $category_id = $product_detail->category_id;
        // Default to China for similar products
        $country = 'China';

        $similar_products = Products::raw(function ($collection) use ($id, $category_id, $country) {
            return $collection->aggregate([
                [
                    '$match' => [
                        'category_id' => $category_id,
                        'country' => $country,
                    ]
                ],
                ['$sample' => ['size' => 4]]
            ]);
        });

        $randomIds = collect($similar_products)->pluck('_id')->toArray();

        $similar_products = Products::whereIn('_id', $randomIds)
            ->with('category')
            ->get()
            ->map(function ($product) {
                $product->_id = (string) $product->_id;
                return $product;
            });

        $product_detail->_id = (string) $product_detail->_id;

        return Inertia::render('ProductDetail', [
            "product_detail" => $product_detail,
            "similar_products" => $similar_products,
        ]);
    }

    public function filter_country_products(Request $request)
    {
        $country = $request->input('country', 'China');
        $product_id = $request->input('product_id');

        $product = Products::where('_id', $product_id)->first();

        if (!$product) {
            return response()->json(['similar_products' => []]);
        }

        $category_id = $product->category_id;

        $similar_products = Products::raw(function ($collection) use ($product_id, $category_id, $country) {
            return $collection->aggregate([
                [
                    '$match' => [
                        'category_id' => $category_id,
                        'country' => $country,
                    ]
                ],
                ['$sample' => ['size' => 4]]
            ]);
        });

        $randomIds = collect($similar_products)->pluck('_id')->toArray();

        $similar_products = Products::whereIn('_id', $randomIds)
            ->with('category')
            ->get()
            ->map(function ($product) {
                $product->_id = (string) $product->_id;
                return $product;
            });

        return response()->json([
            'similar_products' => $similar_products,
        ]);
    }

    public function search_products(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Products::raw(function ($collection) use ($query) {
            return $collection->aggregate([
                [
                    '$match' => [
                        '$or' => [
                            ['title' => ['$regex' => $query, '$options' => 'i']],
                            ['make_title' => ['$regex' => $query, '$options' => 'i']],
                            ['country' => ['$regex' => $query, '$options' => 'i']],
                        ]
                    ]
                ],
                ['$limit' => 10],
                [
                    '$lookup' => [
                        'from' => 'categories',
                        'localField' => 'category_id',
                        'foreignField' => '_id',
                        'as' => 'category'
                    ]
                ],
                [
                    '$lookup' => [
                        'from' => 'categories',
                        'localField' => 'make_id',
                        'foreignField' => '_id',
                        'as' => 'make'
                    ]
                ],
                [
                    '$project' => [
                        'id' => ['$toString' => '$_id'],
                        '_id' => 1,
                        'title' => 1,
                        'front_image' => 1,
                        'make' => ['$arrayElemAt' => ['$make', 0]],
                        'country' => 1,
                        'price' => 1
                    ]
                ]
            ]);
        });

        $results = collect($products)->map(function ($product) {
            return [
                'id' => $product['id'] ?? (string) $product['_id'],
                'title' => $product['title'] ?? '',
                'front_image' => $product['front_image'] ?? '',
                'make' => $product['make'] ? ['cat_title' => $product['make']['cat_title'] ?? ''] : null,
                'country' => $product['country'] ?? '',
                'price' => $product['price'] ?? 0
            ];
        })->values();

        return response()->json($results);
    }
}
