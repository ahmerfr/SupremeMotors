<?php

namespace App\Services;

/**
 * Pure array->product mapping for the autotrader.co.uk search gateway.
 *
 * Unlike the .za scraper this source is SEARCH-GATEWAY ONLY: the
 * SearchResultsListingsGridQuery response already carries a complete product
 * per listing (make/model/year, price, mileage, images, dealer, location), so
 * no per-car detail (FPA) call is ever made. Each `SearchListing` maps straight
 * to the products columns here.
 *
 * Kept pure + side-effect free so it is unit-testable against a saved gateway
 * JSON fixture (tests/Fixtures/autotraderuk/search-response.json).
 */
class AutotraderUkParser
{
    public const BASE = 'https://www.autotrader.co.uk';

    /** the CDN origin every search image is served from (…/{resize}/…jpg) */
    public const IMAGE_HOST = 'm.atcdn.co.uk';

    /** the {resize} placeholder the search response leaves in image URLs */
    private const IMAGE_RESIZE = 'w800h600';

    /**
     * Listing `type`s that are real, bankable cars. GPT_LISTING is a pure ad
     * slot (no advertId); YOU_MAY_ALSO_LIKE / CAMPAIGN rows are cross-sell
     * repeats of a card already in the grid — both are dropped. NATURAL,
     * FEATURED and PROMOTED all carry a real advertId + price.
     */
    private const KEEP_TYPES = ['NATURAL_LISTING', 'FEATURED_LISTING', 'PROMOTED_LISTING'];

    /**
     * Unwrap the gateway response envelope into searchResults. The gateway is
     * hit with a JSON-array (batched) body so it answers with a one-element
     * array `[{"data":{...}}]`; a plain `{"data":{...}}` object is accepted too.
     *
     * @return array{listings: array<int,array<string,mixed>>, last_page: int|null, total: int|null}
     */
    public function parseSearchResponse(array $payload): array
    {
        $root = array_is_list($payload) ? ($payload[0] ?? []) : $payload;
        $sr = $root['data']['searchResults'] ?? null;
        if (!is_array($sr)) {
            return ['listings' => [], 'last_page' => null, 'total' => null];
        }

        $seen = [];
        $listings = [];
        foreach ($sr['listings'] ?? [] as $raw) {
            $mapped = $this->parseListing($raw);
            if ($mapped === null) {
                continue;
            }
            // dedupe: FEATURED cards reappear as YOU_MAY_ALSO_LIKE etc.
            if (isset($seen[$mapped['advert_id']])) {
                continue;
            }
            $seen[$mapped['advert_id']] = true;
            $listings[] = $mapped;
        }

        $page = $sr['page'] ?? [];

        return [
            'listings' => $listings,
            // page.count is the TOTAL number of pages in this filter set
            'last_page' => isset($page['count']) ? (int) $page['count'] : null,
            // results.count is the total number of cars in this filter set
            'total' => isset($page['results']['count']) ? (int) $page['results']['count'] : null,
        ];
    }

    /**
     * Map one search listing to the products-table shape (plus make/model + a
     * few helper keys). Returns null for ad rows, cross-sell repeats, and
     * sold/no-price listings (which are not wanted as available stock).
     *
     * @param  array<string,mixed>  $l
     * @return array<string,mixed>|null
     */
    public function parseListing(array $l): ?array
    {
        $type = $l['type'] ?? null;
        if (!in_array($type, self::KEEP_TYPES, true)) {
            return null; // GPT/YOU_MAY_ALSO_LIKE/CampaignAdvert — not real stock
        }

        $advertId = $l['advertId'] ?? null;
        if (!$advertId) {
            return null;
        }

        $ctx = $l['trackingContext']['advertContext'] ?? [];

        // numeric price is authoritative (the display string carries a £ glyph);
        // treat a missing / zero price as sold/unavailable and skip.
        $price = isset($ctx['price']) ? (int) $ctx['price'] : $this->digits($l['price'] ?? null);
        if (!$price) {
            return null;
        }

        $make = $ctx['make'] ?? null;
        $model = $ctx['model'] ?? null;
        $title = trim((string) ($l['title'] ?? trim(($make ?? '') . ' ' . ($model ?? ''))));
        if ($title === '') {
            return null;
        }

        $subTitle = $l['subTitle'] ?? null;
        $sub = $this->parseSubTitle($subTitle);

        $mileage = null;
        $registeredYear = null;
        foreach ($l['badges'] ?? [] as $badge) {
            $bt = $badge['type'] ?? '';
            $text = $badge['displayText'] ?? '';
            if ($bt === 'MILEAGE') {
                $mileage = $this->digits($text); // miles, stored in the mileage_km column
            } elseif ($bt === 'REGISTERED_YEAR') {
                $registeredYear = $text;
            }
        }

        $images = $this->expandImages($l['images'] ?? []);

        return [
            'advert_id' => (string) $advertId,
            'title' => $title,
            'make' => $make,
            'model' => $model,
            'year' => isset($ctx['year']) ? (int) $ctx['year'] : null,
            'mileage_km' => $mileage,
            'engine_cc' => $sub['engine_cc'],
            'fuel' => $sub['fuel'],
            'transmission' => $sub['transmission'],
            'condition' => $this->normaliseCondition($ctx['condition'] ?? null),
            'steering' => 'Right', // UK = RHD
            'price' => $price, // GBP, stored verbatim
            'country' => 'United Kingdom',
            'website' => 'autotraderuk',
            'body_style' => null, // UK search gateway does not expose a body type
            'product_link' => self::BASE . '/car-details/' . $advertId,
            'images' => $images,
            'specifications' => $this->buildSpecifications($l, $subTitle, $registeredYear, $mileage),
            'product_details' => $this->buildDetailsHtml($l, $sub, $registeredYear),
        ];
    }

    /**
     * Parse the AutoTrader subTitle line (e.g.
     *   "2.0 dCi Dynamique S Nav 4WD Euro 6 (s/s) 5dr"
     *   "1.25 Zetec 5dr"
     *   "2.0 TDI 150 SE Technology 5dr")
     * into engine displacement (cc), fuel (best-effort from engine codes) and
     * transmission (best-effort from auto/manual keywords). Any field that
     * can't be inferred is null — the source simply doesn't state it.
     *
     * @return array{engine_cc: int|null, fuel: string|null, transmission: string|null}
     */
    public function parseSubTitle(?string $subTitle): array
    {
        if ($subTitle === null || trim($subTitle) === '') {
            return ['engine_cc' => null, 'fuel' => null, 'transmission' => null];
        }
        $s = ' ' . strtolower($subTitle) . ' ';

        // engine litres -> cc: leading "2.0", "1.25", "1,6" -> 2000 / 1250 / 1600
        $engineCc = null;
        if (preg_match('/(?<![\d.])(\d\.\d{1,2})(?![\d])/', str_replace(',', '.', $subTitle), $m)) {
            $litres = (float) $m[1];
            if ($litres >= 0.5 && $litres <= 9.9) {
                $engineCc = (int) round($litres * 1000);
            }
        }

        // fuel best-effort from engine badge codes
        $fuel = null;
        $diesel = ['dci', 'tdi', 'hdi', 'cdti', 'crdi', 'tdci', 'bluetec', 'd4d', 'dtec', 'bluehdi', 'cdi', 'tdci', 'sdi'];
        $petrol = ['tsi', 'tfsi', 'vti', 'vvt', 'vtec', 'gdi', 'ecoboost', 'thp', 'gti', 'mpi', 'fsi'];
        foreach ($diesel as $code) {
            if (str_contains($s, ' ' . $code . ' ') || str_contains($s, $code)) {
                $fuel = 'Diesel';
                break;
            }
        }
        if ($fuel === null) {
            foreach ($petrol as $code) {
                if (str_contains($s, $code)) {
                    $fuel = 'Petrol';
                    break;
                }
            }
        }

        // transmission best-effort
        $transmission = null;
        if (str_contains($s, 'automatic') || str_contains($s, ' auto ') || str_contains($s, ' auto)')
            || str_contains($s, ' dsg ') || str_contains($s, ' cvt ') || str_contains($s, ' s tronic')
            || str_contains($s, ' tiptronic')) {
            $transmission = 'Automatic';
        } elseif (str_contains($s, 'manual')) {
            $transmission = 'Manual';
        }

        return ['engine_cc' => $engineCc, 'fuel' => $fuel, 'transmission' => $transmission];
    }

    /**
     * Search image URLs carry a `{resize}` placeholder; swap it for a real size
     * token so the stored URL actually resolves. Non-CDN / malformed entries
     * are dropped. Returns de-duplicated absolute URLs.
     *
     * @param  array<int,mixed>  $images
     * @return string[]
     */
    private function expandImages(array $images): array
    {
        $out = [];
        foreach ($images as $url) {
            if (!is_string($url) || !str_contains($url, self::IMAGE_HOST)) {
                continue;
            }
            $out[] = str_replace('{resize}', self::IMAGE_RESIZE, $url);
        }

        return array_values(array_unique($out));
    }

    /**
     * The full structured field set for the specifications JSON column — every
     * extra fact the search response exposes that doesn't map to a first-class
     * column (raw subTitle, price display, location, dealer, finance, badges,
     * image count, mileage unit note, price indicator, …).
     *
     * @param  array<string,mixed>  $l
     * @return array<string,mixed>
     */
    private function buildSpecifications(array $l, ?string $subTitle, ?string $registeredYear, ?int $mileage): array
    {
        $ctx = $l['trackingContext']['advertContext'] ?? [];
        $features = $l['trackingContext']['advertCardFeatures'] ?? [];
        $distance = $l['trackingContext']['distance'] ?? [];

        $spec = [
            'subTitle' => $subTitle,
            'attentionGrabber' => $l['attentionGrabber'] ?? null,
            'priceDisplay' => $l['price'] ?? null,
            'priceCurrency' => 'GBP',
            'registeredYear' => $registeredYear,
            'mileageUnit' => $mileage !== null ? 'miles' : null,
            'vehicleLocation' => $l['vehicleLocation'] ?? null,
            'locationType' => $l['locationType'] ?? null,
            'distanceMiles' => $distance['distance'] ?? null,
            'sellerType' => $l['sellerType'] ?? null,
            'dealerLink' => $l['dealerLink'] ?? null,
            'dealerReviewRating' => $l['dealerReview']['overallReviewRating'] ?? null,
            'numberOfImages' => $l['numberOfImages'] ?? null,
            'priceIndicator' => $features['priceIndicator'] ?? null,
            'isManufacturerApproved' => $features['isManufacturedApproved'] ?? null,
            'isFranchiseApproved' => $features['isFranchiseApproved'] ?? null,
            'preReg' => $l['preReg'] ?? null,
            'hasDigitalRetailing' => $l['hasDigitalRetailing'] ?? null,
            'rrp' => $l['rrp'] ?? null,
            'discount' => $l['discount'] ?? null,
            'vehicleCategory' => $ctx['vehicleCategory'] ?? null,
            'advertiserType' => $ctx['advertiserType'] ?? null,
            'fpaLink' => $l['fpaLink'] ?? null,
            'listingType' => $l['type'] ?? null,
        ];

        if (!empty($l['finance']['monthlyPrice']['priceFormattedAndRounded'])) {
            $spec['financeMonthly'] = $l['finance']['monthlyPrice']['priceFormattedAndRounded'];
        }

        // drop null entries so the column stays tidy
        return array_filter($spec, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string,mixed>  $l
     * @param  array{engine_cc: int|null, fuel: string|null, transmission: string|null}  $sub
     */
    private function buildDetailsHtml(array $l, array $sub, ?string $registeredYear): string
    {
        $ctx = $l['trackingContext']['advertContext'] ?? [];
        $rows = [];
        $add = function ($label, $value) use (&$rows) {
            $value = trim((string) $value);
            if ($value !== '') {
                $rows[] = '<li><strong>' . e($label) . ':</strong> ' . e($value) . '</li>';
            }
        };
        $add('Make', $ctx['make'] ?? '');
        $add('Model', $ctx['model'] ?? '');
        $add('Variant', $l['subTitle'] ?? '');
        $add('Registered', $registeredYear ?? '');
        $add('Condition', $this->normaliseCondition($ctx['condition'] ?? null) ?? '');
        $add('Location', $l['vehicleLocation'] ?? '');
        if ($sub['engine_cc']) {
            $add('Engine', $sub['engine_cc'] . ' cc');
        }
        $add('Fuel', $sub['fuel'] ?? '');
        $add('Transmission', $sub['transmission'] ?? '');

        return $rows ? '<ul>' . implode('', $rows) . '</ul>' : '';
    }

    private function normaliseCondition(?string $condition): ?string
    {
        if ($condition === null || trim($condition) === '') {
            return null;
        }

        return ucfirst(strtolower(trim($condition))); // "used" -> "Used", "new" -> "New"
    }

    private function digits(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $n = preg_replace('/[^\d]/', '', $value);

        return $n === '' ? null : (int) $n;
    }
}
