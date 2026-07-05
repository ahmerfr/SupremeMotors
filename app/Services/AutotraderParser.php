<?php

namespace App\Services;

/**
 * Pure HTML->array parsing for autotrader.co.za pages. Detail pages carry the
 * full listing as a server-rendered reactRender(...) JSON blob plus a JSON-LD
 * island; both are extracted here so the scrape command stays HTTP-only.
 */
class AutotraderParser
{
    public const BASE = 'https://www.autotrader.co.za';

    public const IMAGE_HOST = 'img.autotrader.co.za';

    /** @return array{listing_urls: string[], last_page: int|null} */
    public function parseSearchPage(string $html): array
    {
        preg_match_all('#href="(/car-for-sale/[^"?]+/\d+)"#', $html, $m);
        $urls = array_values(array_unique(array_map(fn ($p) => self::BASE . $p, $m[1])));

        preg_match_all('/pagenumber=(\d+)/', $html, $pm);
        $last = $pm[1] ? max(array_map('intval', $pm[1])) : null;

        return ['listing_urls' => $urls, 'last_page' => $last];
    }

    /**
     * The search page server-renders its 25 result tiles as a full JSON blob —
     * each tile already carries title, make/model, year, price, mileage, fuel,
     * transmission, condition, the front image and up to 6 gallery shots. So a
     * search-only crawl gets complete products at 1/25th the request count of
     * fetching every detail page.
     *
     * @return array{listings: array<int,array<string,mixed>>, last_page: int|null, total: int|null}
     */
    public function parseSearchListings(string $html): array
    {
        $blob = $this->extractStateBlob($html, 'Components.Desktop_Views_Search_SearchPageView()');
        if (!$blob || !isset($blob['results'])) {
            // fall back to link scraping so a markup change degrades, not dies
            $urls = $this->parseSearchPage($html)['listing_urls'];

            return [
                'listings' => array_map(fn ($u) => ['product_link' => $u, 'images' => []], $urls),
                'last_page' => $this->parseSearchPage($html)['last_page'],
                'total' => null,
            ];
        }

        // organic results first (featured tiles are ads repeated from the grid)
        $tiles = array_merge(
            $blob['results']['results'] ?? [],
            $blob['results']['featuredTiles'] ?? []
        );

        $listings = [];
        foreach ($tiles as $t) {
            $mapped = $this->mapSearchTile($t);
            if ($mapped !== null && !isset($listings[$mapped['listing_id']])) {
                $listings[$mapped['listing_id']] = $mapped; // dedupe featured vs organic
            }
        }

        $pager = $blob['searchPager'] ?? ($blob['results']['searchPager'] ?? []);

        return [
            'listings' => array_values($listings),
            'last_page' => $pager['lastPage'] ?? $pager['totalPages'] ?? null,
            'total' => $pager['totalCount'] ?? null,
        ];
    }

    /** @return array<string,mixed>|null */
    private function mapSearchTile(array $t): ?array
    {
        $id = $t['listingId'] ?? null;
        $url = isset($t['canonicalUrl']) ? self::BASE . $t['canonicalUrl'] : null;
        if (!$id || !$url) {
            return null;
        }

        $title = $t['makeModelLongVariant'] ?? trim(($t['makeModel'] ?? '') . ' ' . ($t['variant'] ?? ''));
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        // classify the summary icons by their svg filename / type
        $mileage = $fuel = $transmission = $condition = null;
        foreach ($t['summaryIcons'] ?? [] as $icon) {
            $iconUrl = $icon['url'] ?? '';
            $text = $this->clean($icon['text'] ?? '');
            if (($icon['type'] ?? 0) === 4) {
                $condition = $text;
            } elseif (str_contains($iconUrl, 'mileage')) {
                $mileage = $this->digits($text);
            } elseif (str_contains($iconUrl, 'transmission')) {
                $transmission = $text;
            } elseif (preg_match('/petrol|diesel|electric|hybrid/i', $iconUrl)) {
                $fuel = $text;
            }
        }

        $images = [];
        if (!empty($t['imageUrl'])) {
            $images[] = $this->stripImageSuffix($t['imageUrl']);
        }
        foreach ($t['standOutImageUrls'] ?? [] as $u) {
            if (is_string($u) && str_contains($u, self::IMAGE_HOST)) {
                $images[] = $this->stripImageSuffix($u);
            }
        }
        $images = array_values(array_unique($images));

        $isPoa = ($t['isPOA'] ?? false) === true;

        return [
            'listing_id' => (int) $id,
            'title' => $title,
            'make' => $t['make'] ?? null,
            'model' => $t['model'] ?? null,
            'year' => isset($t['registrationYear']) ? (int) $t['registrationYear'] : null,
            'mileage_km' => $mileage,
            'fuel' => $fuel,
            'transmission' => $transmission,
            'condition' => $condition ?? ($t['newUsedDescription'] ?? null),
            'steering' => 'Right',
            'price' => $isPoa ? null : $this->digits($t['price'] ?? null),
            'is_poa' => $isPoa,
            'country' => 'South Africa',
            'website' => 'autotrader',
            'body_style' => null,
            'product_link' => $url,
            'dealer' => $t['dealerName'] ?? null,
            'image_count' => $t['imageCount'] ?? count($images),
            'images' => $images,
            'product_details' => $this->buildSearchDetails($t),
        ];
    }

    private function buildSearchDetails(array $t): string
    {
        $rows = [];
        $add = function ($label, $value) use (&$rows) {
            $value = $this->clean((string) $value);
            if ($value !== '') {
                $rows[] = '<li><strong>' . e($label) . ':</strong> ' . e($value) . '</li>';
            }
        };
        $add('Make', $t['make'] ?? '');
        $add('Model', $t['model'] ?? '');
        $add('Variant', $t['variant'] ?? '');
        $add('Condition', $t['newUsedDescription'] ?? '');
        $add('Dealer', $t['dealerName'] ?? '');
        $add('Location', trim(($t['dealerSuburbName'] ?? '') . ' ' . ($t['dealerCityName'] ?? '')));

        return $rows ? '<ul>' . implode('', $rows) . '</ul>' : '';
    }

    private function stripImageSuffix(string $url): string
    {
        // search tiles hand out sized variants (/Fit160x120, /Crop...) — keep the raw id
        return preg_replace('#(' . preg_quote(self::IMAGE_HOST, '#') . '/\d+)(/.*)?$#', '$1', $url);
    }

    private function clean(string $s): string
    {
        return trim(str_replace("\u{a0}", ' ', $s));
    }

    /**
     * @return array<string,mixed>|null attribute map keyed like the products
     *                                  table (plus make/model helper keys), or
     *                                  null when the page has no listing blob
     */
    public function parseDetailPage(string $html, string $url): ?array
    {
        $blob = $this->extractStateBlob($html);
        $ld = $this->extractJsonLd($html);
        if (!$blob && !$ld) {
            return null;
        }

        $icons = collect($blob['summaryIcons'] ?? []);
        $iconText = fn (string $title) => $icons->first(fn ($i) => ($i['title'] ?? '') === $title)['text'] ?? null;

        $title = $blob['header']['registrationYearMakeModelVariant'] ?? ($ld['name'] ?? null);
        $title = $title ? trim(preg_replace('/\s*For Sale$/i', '', $title)) : null;
        if (!$title) {
            return null;
        }

        $priceUrl = $blob['priceInformation']['retailPrice']['financeCalculatorUrl'] ?? '';
        preg_match('/RepaymentPrice=([\d.]+)/', $priceUrl, $pm);
        $price = isset($pm[1]) ? (float) $pm[1] : $this->digits($ld['offers']['price'] ?? null);

        $year = $this->digits($iconText('Registration Year'));
        $mileage = $this->digits($iconText('Mileage'));
        $condition = $icons->first(fn ($i) => ($i['type'] ?? 0) === 4)['text']
            ?? (str_contains($ld['itemCondition'] ?? '', 'New') ? 'New' : 'Used');

        $engineCc = null;
        if (isset($ld['vehicleEngine']['engineDisplacement']['value'])) {
            $engineCc = (int) str_replace(["\u{a0}", ' '], '', strtok($ld['vehicleEngine']['engineDisplacement']['value'], ','));
        }

        $bodyStyle = collect($blob['additionalInformation'] ?? [])
            ->first(fn ($i) => ($i['label'] ?? '') === 'Body Type')['text']
            ?? ($ld['bodyType'] ?? null);

        $drive = $ld['driveWheelConfiguration'] ?? null;
        $drive = $drive ? ltrim($drive, '_') : null;

        $images = collect($blob['gallery']['galleryImages'] ?? [])
            ->pluck('imageUrl')
            ->filter(fn ($u) => is_string($u) && str_contains($u, 'img.autotrader.co.za'))
            ->values()
            ->all();
        if (!$images && isset($ld['image'])) {
            $images = [$ld['image']];
        }

        return [
            'title' => $title,
            'make' => $ld['brand']['name'] ?? null,
            'model' => $ld['model'] ?? null,
            'year' => $year,
            'mileage_km' => $mileage,
            'fuel' => $ld['fuelType'] ?? $iconText('Fuel Type'),
            'transmission' => $ld['vehicleTransmission'] ?? $iconText('Transmission'),
            'condition' => $condition,
            'color' => $ld['color'] ?? null,
            'steering' => 'Right',
            'seats' => isset($ld['seatingCapacity']) ? (int) $ld['seatingCapacity'] : null,
            'doors' => isset($ld['numberOfDoors']) ? (int) $ld['numberOfDoors'] : null,
            'drive_type' => $drive,
            'engine_cc' => $engineCc,
            'price' => $price,
            'country' => 'South Africa',
            'website' => 'autotrader',
            'body_style' => $bodyStyle,
            'product_link' => $url,
            'listing_id' => $blob['listingId'] ?? null,
            'images' => $images,
            'product_details' => $this->buildDetailsHtml($blob, $ld),
            'dealer' => $blob['listingDealer']['name'] ?? null,
        ];
    }

    /** balanced-brace extraction of a reactRender(...) state blob */
    private function extractStateBlob(string $html, string $markerText = 'Components.Desktop_Views_Listing_Listing()'): ?array
    {
        $marker = strpos($html, $markerText);
        if ($marker === false) {
            return null;
        }
        $start = strpos($html, '{', $marker);
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $len = strlen($html);
        for ($i = $start; $i < $len; $i++) {
            $c = $html[$i];
            if ($inString) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($c === '"') {
                $inString = true;
            } elseif ($c === '{') {
                $depth++;
            } elseif ($c === '}' && --$depth === 0) {
                $json = substr($html, $start, $i - $start + 1);

                return json_decode($json, true);
            }
        }

        return null;
    }

    private function extractJsonLd(string $html): ?array
    {
        // the type attribute arrives entity-escaped (ld&#x2B;json)
        if (!preg_match('#<script type="application/ld(?:\+|&\#x2B;)json">(.*?)</script>#s', $html, $m)) {
            return null;
        }
        $data = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);

        return is_array($data) ? $data : null;
    }

    private function buildDetailsHtml(?array $blob, ?array $ld): string
    {
        $parts = [];

        $description = $blob['description'] ?? ($ld['description'] ?? null);
        if ($description) {
            $parts[] = '<p>' . nl2br(e(trim($description))) . '</p>';
        }

        $items = [];
        foreach ($blob['listingSpecifications']['specificationCategories'] ?? [] as $cat) {
            foreach ($cat['categoryItems'] ?? [] as $item) {
                if (($item['name'] ?? '') !== '' && ($item['value'] ?? '') !== '') {
                    $items[] = '<li><strong>' . e($item['name']) . ':</strong> ' . e($item['value']) . '</li>';
                }
            }
        }
        foreach ($blob['additionalInformation'] ?? [] as $item) {
            if (($item['label'] ?? '') !== '' && ($item['text'] ?? '') !== '') {
                $items[] = '<li><strong>' . e($item['label']) . ':</strong> ' . e($item['text']) . '</li>';
            }
        }
        if ($items) {
            $parts[] = '<ul>' . implode('', array_unique($items)) . '</ul>';
        }

        return implode('', $parts);
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
