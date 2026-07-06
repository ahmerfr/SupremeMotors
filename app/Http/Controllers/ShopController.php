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
        $data = Cache::flexible('shop_home_data_v2', [1800, 86400], function () {
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

            $makeCounts = Products::query()
                ->whereNotNull('make_id')
                ->groupBy('make_id')
                ->selectRaw('make_id, COUNT(*) as c')
                ->pluck('c', 'make_id');

            $makes = Categories::where('type', 'make')
                ->select('id', 'cat_title', 'image')
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'cat_title' => $m->cat_title,
                    'image' => $m->image,
                    'products_count' => $makeCounts[$m->id] ?? 0,
                ])
                ->filter(fn ($m) => $m['products_count'] > 0)
                ->sortByDesc('products_count')
                ->values()
                ->toArray();

            // Facets: distinct values (+counts) for every list filter the
            // listing endpoint supports, so the filter UI is data-driven.
            $facet = fn (string $column) => Products::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->selectRaw("`{$column}` as value, COUNT(*) as count")
                ->orderByDesc('count')
                ->get()
                ->toArray();

            $facets = [
                'countries' => $facet('country'),
                'body_styles' => $facet('body_style'),
                'fuels' => $facet('fuel'),
                'transmissions' => $facet('transmission'),
                'conditions' => $facet('condition'),
                'steerings' => $facet('steering'),
                'drive_types' => $facet('drive_type'),
                'emission_standards' => $facet('emission_standard'),
                'year_bounds' => [
                    'min' => (int) Products::whereNotNull('year')->where('year', '>', 1950)->min('year'),
                    'max' => (int) Products::whereNotNull('year')->max('year'),
                ],
            ];

            return [
                'categories' => $categories,
                'makes' => $makes,
                'facets' => $facets,
            ];
        });

        $products = $this->listing();

        return Inertia::render('Shop', [
            'products' => $products,
            'categories' => $data['categories'],
            'makes' => $data['makes'],
            'facets' => $data['facets'],
            // Echo the applied filters so chips/drawer hydrate from the URL.
            'filters' => request()->except(['page']),
        ]);
    }

    /**
     * One filter vocabulary for every caller (hero, chips, drawer, count):
     * comma lists for category/make/country/body_style and the attribute
     * lists, price_min/price_max ranges, plus applyAttributeFilters().
     */
    private function buildListingQuery(array $data)
    {
        // NOTE: the `front_image_dead_at IS NULL` predicate is deliberately NOT
        // here — it's unindexed and only excludes a couple of rows, but it turned
        // the pagination COUNT into a 20s full scan. The count runs on this base
        // (no dead predicate); the dead rows are hidden only on the fetch (where
        // filtering 30 rows is free) — see listing().
        $query = Products::query();

        $csv = fn (?string $v) => ($v !== null && $v !== '') ? array_map('trim', explode(',', $v)) : [];

        $categoryFilters = $csv($data['category'] ?? null);
        // A top-level category also matches products filed under its subcategories.
        if (! empty($categoryFilters)) {
            $categoryFilters = Categories::expandWithChildren($categoryFilters);
        }

        $makeFilters = $csv($data['make'] ?? null);
        $countryFilters = $csv($data['country'] ?? null);
        $bodyStyles = $csv($data['body_style'] ?? null);
        $search = $data['search'] ?? null;

        $query->when($search, fn ($q) => $q->search($search))
            ->when(! empty($categoryFilters), fn ($q) => $q->whereIn('category_id', $categoryFilters))
            ->when(! empty($makeFilters), fn ($q) => $q->whereIn('make_id', $makeFilters))
            ->when(! empty($bodyStyles), fn ($q) => $q->whereIn('body_style', $bodyStyles))
            ->when(! empty($countryFilters), fn ($q) => $q->whereIn('country', $countryFilters));

        $priceMin = $data['price_min'] ?? null;
        $priceMax = $data['price_max'] ?? null;
        if ($priceMin !== null && $priceMin !== '' && $priceMax !== null && $priceMax !== '') {
            $query->whereBetween('price', [(int) $priceMin, (int) $priceMax]);
        } elseif ($priceMin !== null && $priceMin !== '') {
            $query->where('price', '>=', (int) $priceMin);
        } elseif ($priceMax !== null && $priceMax !== '') {
            $query->where('price', '<=', (int) $priceMax);
        }

        $this->applyAttributeFilters($query, $data);

        return $query;
    }

    /**
     * Whitelisted sort orders. Price sorts sink "Enquire" rows (0/null price or
     * a source whose prices the cards don't display) to the bottom — via the
     * indexed `enquire_sort` generated column (0 = show price, 1 = enquire), so
     * `enquire_sort ASC, price` is served straight off the
     * (country|category_id, enquire_sort, price) composite indexes with no
     * filesort. See migration 2026_07_06_130000.
     */
    private function applySort($query, ?string $sort): void
    {
        match ($sort) {
            // price_asc: enquire (0/null-price) rows would otherwise pile at the
            // TOP, so sink them via the indexed enquire_sort (both ASC -> served
            // by the (…, enquire_sort, price) index, no filesort).
            'price_asc' => $query->orderBy('enquire_sort')->orderBy('price'),
            // price_desc: NULL/0 prices are already the smallest, so they sink to
            // the bottom on their own — a plain price DESC is a backward scan of
            // the (…, price) index, no filesort, no computed key.
            'price_desc' => $query->orderByDesc('price'),
            // newest-year first: NULL years are the smallest so they sink to the
            // bottom on their own -> plain year DESC off the year index, no filesort
            'year_desc' => $query->orderByDesc('year'),
            // lowest-mileage first, off the mileage index (no filesort). NULLs
            // sort first but are few; keeping it a pure ORDER BY preserves the
            // sort-independent count cache.
            'mileage_asc' => $query->orderBy('mileage_km'),
            default => $query->orderByDesc('created_at'),
        };
    }

    /** Staged-drawer live count: same query, cached total only. */
    public function count(Request $request)
    {
        $query = $this->buildListingQuery($request->all());
        $countKey = 'listing_count_' . md5(json_encode(collect($request->except(['page', 'sort']))->sortKeys()->all()));
        $total = Cache::flexible($countKey, [3600, 86400], fn () => $query->toBase()->getCountForPagination());

        return response()->json(['total' => $total]);
    }

    /** Only the columns ProductCard.vue renders — never the huge blobs. */
    private const CARD_COLUMNS = [
        'id', 'title', 'make_id', 'category_id', 'country', 'fuel',
        'transmission', 'mileage_km', 'price', 'website', 'front_image', 'other_images',
    ];

    public function listing()
    {
        $request = request();
        // base query WITHOUT the dead-image predicate -> the COUNT stays index-fast
        $query = $this->buildListingQuery($request->all());
        $this->applySort($query, $request->input('sort'));
        $page = max(1, (int) $request->input('page', 1));

        // per-filter totals barely move on an 838K catalogue; cache them long.
        $countKey = 'listing_count_' . md5(json_encode(collect($request->except(['page', 'sort']))->sortKeys()->all()));
        $total = Cache::flexible($countKey, [3600, 86400], fn () => (clone $query)->toBase()->getCountForPagination());

        // fetch: lean column set (no product_details/specifications/*_source
        // blobs = ~90KB/page saved) + hide the handful of dead-image rows here,
        // where filtering 30 rows is free.
        $rows = (clone $query)
            ->whereNull('front_image_dead_at')
            ->with(['category:id,cat_title', 'make:id,cat_title'])
            ->forPage($page, 30)
            ->get(self::CARD_COLUMNS);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $rows,
            $total,
            30,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
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
        $product_detail = Products::with(['category', 'make:id,cat_title'])
            ->when(
                ctype_digit((string) $id),
                fn ($q) => $q->where('id', $id),
                fn ($q) => $q->where('mongo_id', $id) // legacy Mongo URLs
            )
            ->first();

        if (!$product_detail) {
            abort(404);
        }

        // Similar stock from the same category and origin as this unit
        $similar_products = $this->randomSimilarProducts($product_detail->category_id, $product_detail->country ?? 'China', $product_detail->id);

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
    private function randomSimilarProducts(?int $categoryId, string $country, int $excludeId, int $limit = 3)
    {
        if ($categoryId === null) {
            return collect();
        }

        // ORDER BY RAND() (and min/max/offset) over the WHOLE (category,country)
        // group — ~450k rows for UK Cars — costs seconds. Instead cache a small
        // pool of recent candidate ids per (category,country): `latest('id')`
        // reads only ~60 entries off the tail of the (category_id, country)
        // index (fast), the pool is cached 1h (stale-while-revalidate so the
        // rebuild never blocks a request), and we shuffle-pick in PHP.
        $pool = Cache::flexible(
            'similar_pool_' . $categoryId . '_' . md5($country),
            [3600, 86400],
            fn () => Products::query()
                ->where('category_id', $categoryId)
                ->where('country', $country)
                ->whereNotNull('front_image')
                ->whereNull('front_image_dead_at')
                ->latest('id')
                ->limit(60)
                ->pluck('id')
                ->all()
        );

        $ids = collect($pool)->reject(fn ($i) => $i === $excludeId)->shuffle()->take($limit);
        if ($ids->isEmpty()) {
            return collect();
        }

        return Products::whereIn('id', $ids)
            ->with(['category:id,cat_title', 'make:id,cat_title'])
            ->get(self::CARD_COLUMNS);
    }

    public function search_products(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // FULLTEXT via the shared search scope — LIKE '%term%' was a full
        // table scan and would take seconds at the 2M-product target.
        $products = Products::query()
            ->with('make:id,cat_title')
            ->whereNull('front_image_dead_at')
            ->search($query)
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
