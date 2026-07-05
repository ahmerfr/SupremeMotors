<?php

namespace App\Services;

/**
 * Phase-2 parser for the autotrader.co.uk car-details (FPA) page.
 *
 * The search gateway (see AutotraderUkParser) only carries ~4 images and no
 * body/fuel/transmission/engine, so Phase 2 fetches the SSR car-details HTML and
 * pulls the FULL gallery + the structured spec sheet out of it. `parseDetail()`
 * is pure + side-effect free so it is unit-testable against the saved fixture
 * (tests/Fixtures/autotraderuk/detail-page.html).
 *
 * Isolating the MAIN car from the ~related-car cross-sell noise is the whole
 * game here — the page carries, besides the car being viewed:
 *   - a "you may also like" rail whose cars each ship their own escaped-JSON
 *     block with an `imageList`, `specificationData` and a *different* advertId;
 *   - sized dupes of every image (w340/w480/w600/w720/w800 of one media hash).
 * Two clean anchors defeat both:
 *   1. GALLERY images come ONLY from the rendered `<section data-testid="gallery">`
 *      carousel (the related-car rail is a different section, and its images live
 *      in JSON `imageList`s, never in this section). Dedup by the 32-char media
 *      hash; normalise every size token to one canonical size.
 *   2. STRUCTURED specs come from the FIRST `advertContext`+`vehicleContext`
 *      escaped-JSON block whose advertId matches the URL — that is the car being
 *      viewed. Related cars have a distinct `specificationData` shape and their
 *      own advertIds, so anchoring on the URL's advertId picks the right one.
 * Doors/seats aren't in the main vehicleContext JSON, so they come from the
 * rendered key-facts strip (`<p>Doors</p><p>5</p>`), which is main-car-only.
 */
class AutotraderUkDetailParser
{
    /** the CDN origin every gallery image is served from */
    public const IMAGE_HOST = 'm.atcdn.co.uk';

    /** one canonical size token so w340/w480/…/w800 of a hash collapse to one URL */
    private const CANON_SIZE = 'w800';

    /**
     * Parse a car-details SSR page into the products-shape field map (same keys
     * the search parser returns, so bankListing/mapToProduct consume it as-is),
     * merged over the search-tier row. Returns null when the page carries no
     * recognisable car (challenge page, empty body, etc.).
     *
     * @return array<string,mixed>|null
     */
    public function parseDetail(string $html, string $url): ?array
    {
        if (trim($html) === '') {
            return null;
        }

        $advertId = $this->advertIdFromUrl($url);
        $ctx = $this->mainVehicleJson($html, $advertId);
        $panel = $this->keyFactsPanel($html);
        $images = $this->galleryImages($html);

        // nothing structured AND no gallery => not a real detail page
        if ($ctx === null && $panel === [] && $images === []) {
            return null;
        }

        $advert = $ctx['advert'] ?? [];
        $vehicle = $ctx['vehicle'] ?? [];

        $make = $vehicle['standardMake'] ?? $advert['make'] ?? null;
        $model = $vehicle['standardModel'] ?? $advert['model'] ?? null;

        $bodyType = $vehicle['bodyType'] ?? $panel['Body type'] ?? null;
        $fuel = $vehicle['fuelType'] ?? $panel['Fuel type'] ?? null;
        $transmission = $vehicle['transmission'] ?? $panel['Gearbox'] ?? null;
        $colour = $vehicle['colour'] ?? $panel['Body colour'] ?? null;

        $engineCc = isset($vehicle['standardEngineSizeCC']) ? (int) $vehicle['standardEngineSizeCC'] : null;

        $doors = $this->firstInt($panel['Doors'] ?? null);
        $seats = $this->firstInt($panel['Seats'] ?? null);

        $mileage = isset($advert['mileage']) ? (int) $advert['mileage'] : null;
        $year = isset($advert['year']) ? (int) $advert['year'] : null;
        $price = isset($advert['price']) ? (int) $advert['price'] : null;
        $condition = isset($advert['condition']) ? ucfirst(strtolower((string) $advert['condition'])) : null;

        $title = trim((string) (($make ?? '') . ' ' . ($model ?? '')));

        $out = [
            'title' => $title !== '' ? $title : null,
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'mileage_km' => $mileage,          // miles, stored in mileage_km (unit noted in specs)
            'engine_cc' => $engineCc,
            'fuel' => $fuel,
            'transmission' => $transmission,
            'condition' => $condition,
            'color' => $colour,
            'steering' => 'Right',             // UK = RHD
            'seats' => $seats,
            'doors' => $doors,
            'drive_type' => $vehicle['standardDrivetrain'] ?? null,
            'body_style' => $bodyType,
            'price' => $price,
            'country' => 'United Kingdom',
            'website' => 'autotraderuk',
            'product_link' => $url,
            'images' => $images,
            'specifications' => $this->buildSpecifications($advert, $vehicle, $panel, $images),
        ];

        // drop nulls so a merge over the search-tier row never clobbers a good
        // value with a null — but ALWAYS keep images + specifications (the
        // specifications array is the "enriched" marker Phase 2 gates on).
        $keep = ['images' => $out['images'], 'specifications' => $out['specifications']];

        return array_merge(
            array_filter($out, fn ($v) => $v !== null && $v !== '' && $v !== []),
            $keep
        );
    }

    /** the numeric advertId from a /car-details/{id} URL, or null */
    private function advertIdFromUrl(string $url): ?string
    {
        if (preg_match('#/car-details/(\d+)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * The main car's advertContext + vehicleContext, decoded from the escaped
     * JSON the page ships. We anchor on the advertId from the URL so we pick the
     * car being VIEWED, not a related-car block. When the URL has no id (or it
     * isn't found) we fall back to the FIRST advertContext, which the page
     * renders for the main car before any cross-sell rail.
     *
     * @return array{advert: array<string,mixed>, vehicle: array<string,mixed>}|null
     */
    private function mainVehicleJson(string $html, ?string $advertId): ?array
    {
        // the blocks are double-escaped (\" inside a JSON string in the HTML);
        // unescape just enough to run a JSON decode on the slice we cut out.
        $anchor = '"advertContext":{';
        $flat = str_replace('\\"', '"', $html);

        $start = false;
        if ($advertId !== null) {
            $needle = '"advertContext":{"advertId":"' . $advertId . '"';
            $pos = strpos($flat, $needle);
            if ($pos !== false) {
                $start = $pos;
            }
        }
        if ($start === false) {
            $pos = strpos($flat, $anchor);
            $start = $pos === false ? false : $pos;
        }
        if ($start === false) {
            return null;
        }

        $advert = $this->extractObject($flat, $start + strlen('"advertContext":'));
        if ($advert === null) {
            return null;
        }

        $vehicle = [];
        $vPos = strpos($flat, '"vehicleContext":{', $start);
        if ($vPos !== false) {
            $vehicle = $this->extractObject($flat, $vPos + strlen('"vehicleContext":')) ?? [];
        }

        return ['advert' => $advert, 'vehicle' => $vehicle];
    }

    /**
     * Cut the balanced { … } object beginning at $braceStart out of $s and
     * json_decode it. Returns null if the braces don't balance or it won't decode.
     *
     * @return array<string,mixed>|null
     */
    private function extractObject(string $s, int $braceStart): ?array
    {
        $open = strpos($s, '{', $braceStart);
        if ($open === false) {
            return null;
        }
        $depth = 0;
        $inStr = false;
        $len = strlen($s);
        for ($i = $open; $i < $len; $i++) {
            $ch = $s[$i];
            if ($inStr) {
                if ($ch === '\\') {
                    $i++; // skip escaped char
                } elseif ($ch === '"') {
                    $inStr = false;
                }

                continue;
            }
            if ($ch === '"') {
                $inStr = true;
            } elseif ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $json = substr($s, $open, $i - $open + 1);
                    $decoded = json_decode($json, true);

                    return is_array($decoded) ? $decoded : null;
                }
            }
        }

        return null;
    }

    /**
     * The rendered key-facts strip — `<p …>Label</p><p …>Value</p>` pairs for the
     * MAIN car only (Registration, Fuel type, Body type, Engine, Gearbox, Doors,
     * Seats, Body colour, Emission class). Related cars don't use this markup.
     *
     * @return array<string,string>
     */
    private function keyFactsPanel(string $html): array
    {
        $labels = 'Registration|Mileage|Fuel type|Body type|Engine|Gearbox|Doors|Seats|Body colour|Emission class|Owners';
        preg_match_all(
            '#<p class="[^"]*">(' . $labels . ')</p><p class="[^"]*">([^<]+)</p>#',
            $html,
            $m,
            PREG_SET_ORDER
        );

        $out = [];
        foreach ($m as $pair) {
            $key = $pair[1];
            if (!isset($out[$key])) { // first (main-car) wins
                $out[$key] = html_entity_decode(trim($pair[2]), ENT_QUOTES);
            }
        }

        return $out;
    }

    /**
     * The MAIN car's full gallery. Images are read only from the rendered
     * `<section … data-testid="gallery" …>` carousel, so related-car rail images
     * (which live in JSON imageLists, never in this section) are excluded by
     * construction. Sized dupes (w340…w800 of one hash) collapse to one
     * canonical-size URL, deduped by the 32-char media hash and kept in gallery
     * order.
     *
     * @return string[]
     */
    private function galleryImages(string $html): array
    {
        $section = $this->gallerySection($html);
        if ($section === null) {
            return [];
        }

        preg_match_all(
            '#https://' . preg_quote(self::IMAGE_HOST, '#') . '/a/media/(?:w\d+/)?([0-9a-f]{32})\.jpg#i',
            $section,
            $m,
            PREG_SET_ORDER
        );

        $out = [];
        $seen = [];
        foreach ($m as $hit) {
            $hash = strtolower($hit[1]);
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;
            $out[] = 'https://' . self::IMAGE_HOST . '/a/media/' . self::CANON_SIZE . '/' . $hash . '.jpg';
        }

        return $out;
    }

    /**
     * Slice the `<section … data-testid="gallery" …> … </section>` body out of
     * the page with a balanced-tag walk (styled-components nest sections, so a
     * naive `</section>` stops too early). Returns null when there's no gallery.
     */
    private function gallerySection(string $html): ?string
    {
        $start = false;
        if (preg_match('#<section[^>]*data-testid="gallery"[^>]*>#', $html, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
        }
        if ($start === false) {
            return null;
        }

        $bodyStart = $start + strlen($m[0][0]);
        $depth = 1;
        $len = strlen($html);
        $pos = $bodyStart;
        while ($depth > 0 && $pos < $len) {
            $open = strpos($html, '<section', $pos);
            $close = strpos($html, '</section>', $pos);
            if ($close === false) {
                break;
            }
            if ($open !== false && $open < $close) {
                $depth++;
                $pos = $open + 8;
            } else {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $bodyStart, $close - $bodyStart);
                }
                $pos = $close + 10;
            }
        }

        return substr($html, $bodyStart, min($len, $bodyStart + 40000) - $bodyStart);
    }

    /**
     * Everything extra the detail page exposes that doesn't map to a first-class
     * column, for the specifications JSON. Always a non-empty array on a real
     * page, so a NULL specifications column stays the "not-yet-enriched" marker.
     *
     * @param  array<string,mixed>  $advert
     * @param  array<string,mixed>  $vehicle
     * @param  array<string,string>  $panel
     * @param  string[]  $images
     * @return array<string,mixed>
     */
    private function buildSpecifications(array $advert, array $vehicle, array $panel, array $images): array
    {
        $spec = [
            'source' => 'detail',
            'mileageUnit' => isset($advert['mileage']) ? 'miles' : null,
            'priceCurrency' => 'GBP',
            'registration' => $panel['Registration'] ?? null,
            'emissionClass' => $vehicle['emissionClass'] ?? ($panel['Emission class'] ?? null),
            'trim' => $vehicle['standardTrim'] ?? null,
            'drivetrain' => $vehicle['standardDrivetrain'] ?? null,
            'engineSizeLitres' => $vehicle['standardEngineSizeLitres'] ?? null,
            'isCrossover' => $vehicle['isCrossover'] ?? null,
            'advertiserType' => $advert['advertiserType'] ?? null,
            'vehicleCheckStatus' => $advert['vehicleCheckStatus'] ?? null,
            'hasHomeDelivery' => $advert['hasHomeDelivery'] ?? null,
            'owners' => $panel['Owners'] ?? null,
            'numberOfImages' => count($images),
            'categoryTags' => $advert['categoryTags'] ?? null,
        ];

        $spec = array_filter($spec, fn ($v) => $v !== null && $v !== '' && $v !== []);

        // guarantee a non-empty array even for a threadbare page, so the column
        // is never accidentally re-decoded to NULL and re-enriched forever.
        if ($spec === []) {
            $spec = ['source' => 'detail'];
        }

        return $spec;
    }

    private function firstInt(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (preg_match('/\d+/', $value, $m)) {
            return (int) $m[0];
        }

        return null;
    }
}
