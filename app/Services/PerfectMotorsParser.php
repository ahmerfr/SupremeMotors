<?php

namespace App\Services;

/**
 * Pure HTML->array parsing for perfect-motors.com productDetail pages. Each car
 * is a server-rendered detail page (there is no JSON island); the spec block,
 * the main-gallery carousel, the price and the title <h1> are all read straight
 * from the markup here so the scrape command stays HTTP-only.
 *
 * Prices are ALREADY in USD on this source ($5,670.00) — stored verbatim, no
 * currency conversion. Stock is UAE export inventory, so country defaults to
 * the listing's Location (United Arab Emirates).
 */
class PerfectMotorsParser
{
    public const BASE = 'https://perfect-motors.com';

    public const IMAGE_HOST = 'perfect-motors.com';

    /**
     * Multi-word makes whose model is otherwise mis-split. The <h1> reads
     * "{year} {make} {model}" with a single space between every token, so a
     * two-word make like "Land Rover" would leak its second word into the model
     * unless we peel it off first. Longest-match wins ("Mercedes Benz AMG GT").
     *
     * @var string[]
     */
    private const TWO_WORD_MAKES = [
        'Land Rover',
        'Range Rover',
        'Mercedes Benz',
        'Mercedes-Benz',
        'Aston Martin',
        'Alfa Romeo',
        'Rolls Royce',
        'Great Wall',
        'Mini Cooper',
    ];

    /**
     * @return array<string,mixed>|null attribute map the scrape command's
     *                                  mapToProduct consumes, or null when the
     *                                  page is not a real car (500/empty)
     */
    public function parseDetailPage(string $html, string $url): ?array
    {
        // a real car always carries the <h1 class="protitle"> heading; an
        // invalid/deleted id renders a 500/error shell without it
        if (!preg_match('#<h1[^>]*class="protitle"[^>]*>(.*?)</h1>#is', $html, $tm)) {
            return null;
        }
        $title = $this->clean(strip_tags($tm[1]));
        if ($title === '') {
            return null;
        }

        [$year, $make, $model] = $this->splitTitle($title);

        // the full label:value spec block (deduped across the mobile+desktop copies)
        $specs = $this->buildSpecifications($html);

        $mileage = $this->digits($specs['Milage'] ?? $specs['Mileage'] ?? null);
        $engineCc = $this->digits($specs['Engine'] ?? null);
        $seats = $this->digits($specs['Seating Capacity'] ?? null);
        $doors = $this->digits($specs['Number of Doors'] ?? null);
        $steering = $this->steering($specs['Steering'] ?? null);

        // year comes off the title first (clean integer); fall back to the spec table
        if ($year === null) {
            $year = $this->digits($specs['Year'] ?? null);
        }

        $images = $this->galleryImages($html);

        return [
            'title' => $title,
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'mileage_km' => $mileage,
            'fuel' => $this->clean($specs['Fuel'] ?? '') ?: null,
            'transmission' => $this->clean($specs['Transmission'] ?? '') ?: null,
            'condition' => 'Used',
            'color' => $this->clean($specs['Color'] ?? $specs['Colour'] ?? '') ?: null,
            'steering' => $steering,
            'seats' => $seats,
            'doors' => $doors,
            'drive_type' => $this->clean($specs['Drive Type'] ?? $specs['Drive'] ?? '') ?: null,
            'engine_cc' => $engineCc,
            'price' => $this->extractPrice($html),
            'country' => $this->clean($specs['Location'] ?? '') ?: 'United Arab Emirates',
            'website' => 'perfectmotors',
            'body_style' => $this->clean($specs['Body Type'] ?? $specs['Body Style'] ?? '') ?: null,
            'product_link' => $url,
            'images' => $images,
            'product_details' => $this->buildDetailsHtml($specs, $html),
            // always an array on success (even when empty) so specifications IS
            // NULL cleanly means "detail not fetched yet" and drives fill-incomplete
            'specifications' => $specs,
        ];
    }

    /**
     * Split "1995 Nissan Homy" -> [1995, 'Nissan', 'Homy'].
     * First token is the year, then a make (one or two words per the known
     * list), and everything after is the model.
     *
     * @return array{0:int|null,1:string|null,2:string|null}
     */
    private function splitTitle(string $title): array
    {
        $title = $this->clean($title);
        $year = null;
        if (preg_match('/^\s*(\d{4})\b\s*/', $title, $m)) {
            $year = (int) $m[1];
            $title = trim(substr($title, strlen($m[0])));
        }
        if ($title === '') {
            return [$year, null, null];
        }

        foreach (self::TWO_WORD_MAKES as $twoWord) {
            if (stripos($title . ' ', $twoWord . ' ') === 0) {
                $make = substr($title, 0, strlen($twoWord));
                $model = trim(substr($title, strlen($twoWord)));

                return [$year, $make, $model !== '' ? $model : null];
            }
        }

        $parts = preg_split('/\s+/', $title, 2);
        $make = $parts[0] ?? null;
        $model = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null;

        return [$year, $make, $model];
    }

    /**
     * The full detail spec table(s). The page renders the same tblcon table
     * twice (mobile + desktop copies) — first-writer-wins dedup keeps one clean
     * label=>value map of every row on the page. Also folds in the "Features"
     * equipment grid as a single comma-joined value.
     *
     * @return array<string,string>
     */
    private function buildSpecifications(string $html): array
    {
        $out = [];
        // strip HTML comments first: the page ships a commented-out spec table
        // whose stray <td>Mileage</td> would otherwise pair with a later live
        // <th> and inject a garbage row
        $clean = preg_replace('/<!--.*?-->/s', '', $html);
        // each spec row is <tr><td>Label</td><th>Value</th></tr>; the markup has
        // a couple of malformed </tr (missing '>') so match forgivingly. Anchor
        // the value to the NEXT <th> only (no <td> between) so a label never
        // reaches across an unrelated cell to grab a distant value.
        if (preg_match_all('#<td[^>]*>((?:(?!</td>).)*?)</td>\s*<th[^>]*>((?:(?!</th>).)*?)</th>#is', $clean, $rows, PREG_SET_ORDER)) {
            foreach ($rows as $r) {
                $label = $this->clean(strip_tags($r[1]));
                $value = $this->clean(strip_tags($r[2]));
                if ($label !== '' && $value !== '' && !isset($out[$label])) {
                    $out[$label] = $value;
                }
            }
        }

        $features = $this->extractFeatures($clean);
        if ($features !== [] && !isset($out['Features'])) {
            $out['Features'] = implode(', ', $features);
        }

        return $out;
    }

    /**
     * The "Features" section is a flat equipment grid (table.tblborder). Pull
     * the cell labels as a deduped list of fitted options.
     *
     * @return string[]
     */
    private function extractFeatures(string $html): array
    {
        if (!preg_match('#<table[^>]*class="[^"]*tblborder[^"]*"[^>]*>(.*?)</table>#is', $html, $m)) {
            return [];
        }
        if (!preg_match_all('#<td[^>]*>(.*?)</td>#is', $m[1], $cells)) {
            return [];
        }
        $out = [];
        foreach ($cells[1] as $cell) {
            $text = $this->clean(strip_tags($cell));
            if ($text !== '' && !in_array($text, $out, true)) {
                $out[] = $text;
            }
        }

        return $out;
    }

    /**
     * Isolate ONLY the main product gallery. The detail page carries three image
     * blocks: the main carousel (div.thumb-gallery-detail), a thumbnail strip
     * that repeats it (div.thumb-gallery-thumbs), and a "Similar & Related
     * Vehicles" carousel of OTHER cars' thumbs. We slice out just the
     * thumb-gallery-detail block (up to where the thumbs strip begins) so the
     * related cars never contaminate this car's gallery, then dedup image ids.
     *
     * The _thumb variant always exists (the full-size sometimes 404s), so we
     * store the _thumb URLs directly. The extension varies by car — older stock
     * is _thumb.jpeg, newer is _thumb.jpg (and occasionally png/webp) — so we
     * capture and preserve whatever extension the page actually uses.
     *
     * @return string[]
     */
    private function galleryImages(string $html): array
    {
        $block = $html;
        if (preg_match('#<div[^>]*class="[^"]*thumb-gallery-detail[^"]*"[^>]*>(.*?)<div[^>]*class="[^"]*thumb-gallery-thumbs[^"]*"#is', $html, $m)) {
            $block = $m[1];
        } elseif (preg_match('#<div[^>]*id="mainconofcimages"[^>]*>(.*?)</div>\s*</div>\s*<div[^>]*class="col-xl-8#is', $html, $m2)) {
            // fallback: the whole main-image column, still before the related cars
            $block = $m2[1];
        }

        preg_match_all('#/admin-assets/images/([A-Za-z0-9_-]+)_thumb\.(jpe?g|png|webp)#i', $block, $mm, PREG_SET_ORDER);

        $images = [];
        $seen = [];
        foreach ($mm as $m) {
            $id = $m[1];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $images[] = self::BASE . '/admin-assets/images/' . $id . '_thumb.' . strtolower($m[2]);
        }

        return $images;
    }

    /** the price is USD, in .propricemaincon -> <h6>$5,670.00</h6> */
    private function extractPrice(string $html): ?float
    {
        if (preg_match('#propricemaincon.*?<h6>\s*\$?\s*([\d,]+(?:\.\d+)?)#is', $html, $m)) {
            $n = (float) str_replace(',', '', $m[1]);

            return $n > 0 ? $n : null;
        }

        return null;
    }

    /** "RHD" -> "Right", "LHD" -> "Left" (else pass through / null) */
    private function steering(?string $raw): ?string
    {
        $raw = $this->clean((string) $raw);
        if ($raw === '') {
            return null;
        }
        $u = strtoupper($raw);
        if (str_contains($u, 'RHD') || str_contains($u, 'RIGHT')) {
            return 'Right';
        }
        if (str_contains($u, 'LHD') || str_contains($u, 'LEFT')) {
            return 'Left';
        }

        return $raw;
    }

    /** @param array<string,string> $specs */
    private function buildDetailsHtml(array $specs, string $html): string
    {
        $rows = [];
        foreach ($specs as $label => $value) {
            if ($label === 'Features') {
                continue; // rendered separately below
            }
            $rows[] = '<li><strong>' . e($label) . ':</strong> ' . e($value) . '</li>';
        }
        $parts = [];
        if ($rows) {
            $parts[] = '<ul>' . implode('', $rows) . '</ul>';
        }
        if (isset($specs['Features']) && $specs['Features'] !== '') {
            $parts[] = '<h5>Features</h5><p>' . e($specs['Features']) . '</p>';
        }

        return implode('', $parts);
    }

    private function clean(string $s): string
    {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5);
        $s = str_replace("\u{a0}", ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
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
