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

    /** balanced-brace extraction of the reactRender listing state */
    private function extractStateBlob(string $html): ?array
    {
        $marker = strpos($html, 'Components.Desktop_Views_Listing_Listing()');
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
