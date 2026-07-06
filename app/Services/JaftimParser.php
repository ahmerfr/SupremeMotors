<?php

namespace App\Services;

/**
 * Parse a jaftim.com stock-listing page.
 *
 * The listing page embeds the FULL record of every car on the page as a JS array
 * `var arrStock = [ {...136 fields...}, ... ]` — make/model/year/mileage/colour/
 * body/engine/drive/doors/transmission/features/description — so no per-car detail
 * fetch is needed for the data. The one field NOT trustworthy in arrStock is the
 * price (its stock_fob_price is an AED figure whose displayed USD is per-car, not a
 * flat 3.6725 divide), so the authoritative USD price + the canonical detail URL are
 * read from the visible card HTML and matched to arrStock by stock_id.
 *
 * Gallery images live at erp.jaftim.com/storage/app/public/stock/<stock_id>/f.jpg
 * (front) and 1.jpg, 2.jpg, … — parseGalleryImages() reads the real list off a
 * detail page.
 */
class JaftimParser
{
    private const IMG_BASE = 'https://erp.jaftim.com/storage/app/public/stock/';

    private const SITE = 'https://www.jaftim.com/';

    private const AED_USD = 3.6725;

    /**
     * @return list<array<string,mixed>>  one normalised row per car on the page
     */
    public function parseListing(string $html): array
    {
        $stock = $this->extractArrStock($html);
        if ($stock === []) {
            return [];
        }
        $cards = $this->extractCards($html);   // stock_id => ['url'=>, 'price'=>]

        $rows = [];
        foreach ($stock as $s) {
            $sid = (string) ($s['stock_id'] ?? '');
            if ($sid === '') {
                continue;
            }
            // skip SOLD cars — jaftim leaves them in the listing (sold=1, no price).
            // sold=0 is the available, priced inventory (exactly the cars with a
            // USD price on the card).
            if ((int) ($s['sold'] ?? 0) === 1) {
                continue;
            }
            $card = $cards[$sid] ?? [];
            $rows[] = $this->mapCar($s, $card, $sid);
        }

        return $rows;
    }

    /**
     * The `var arrStock = [...]` product array. Extracted with strpos, NOT a
     * `.*?` regex — the array is multi-MB and would blow PHP's pcre.backtrack_limit.
     * The first `];` after the opening `[` is the real terminator (no `];` occurs
     * inside the JSON values).
     */
    private function extractArrStock(string $html): array
    {
        $at = strpos($html, 'var arrStock');
        if ($at === false) {
            return [];
        }
        $open = strpos($html, '[', $at);
        if ($open === false) {
            return [];
        }
        $end = strpos($html, '];', $open);
        if ($end === false) {
            return [];
        }
        $json = substr($html, $open, $end - $open + 1);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Visible cards -> stock_id => [url, price(USD int|null)]. A card without a
     * "USD n" price is POA (price stays null -> Enquire).
     *
     * @return array<string,array{url:string,price:?int}>
     */
    private function extractCards(string $html): array
    {
        $out = [];
        // split into per-card chunks so a card's price can't bleed from the next
        $chunks = preg_split('/(?=<div class="listing-car-item)/', $html);
        foreach ((array) $chunks as $c) {
            if (!preg_match('#href="(used-[a-z0-9-]+/[a-z0-9-]+/(\d+))"#i', $c, $hm)) {
                continue;
            }
            $sid = $hm[2];
            $price = null;
            if (preg_match('#<b>\s*USD\s*([\d,]+)\s*</b>#i', $c, $pm)) {
                $price = (int) str_replace(',', '', $pm[1]);
            }
            $out[$sid] = ['url' => self::SITE . $hm[1], 'price' => $price];
        }

        return $out;
    }

    /** map one arrStock record (+ its card) onto the products columns */
    private function mapCar(array $s, array $card, string $sid): array
    {
        $make = trim((string) ($s['make_name'] ?? ''));
        $model = trim((string) ($s['model_name'] ?? ''));
        $year = (int) ($s['reg_year'] ?? 0) ?: null;
        $title = trim(implode(' ', array_filter([$make, $model, $year])));

        // authoritative displayed USD price from the card; 0 = price-on-request.
        // (No fob fallback — it fabricates figures; available cars always carry a
        // card price anyway.)
        $price = $card['price'] ?? 0;

        $images = [self::IMG_BASE . $sid . '/f.jpg'];   // front; gallery filled later

        return [
            'stock_id' => $sid,
            'title' => $title !== '' ? $title : ('Jaftim ' . $sid),
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'mileage_km' => isset($s['stock_mileage']) ? (int) $s['stock_mileage'] : null,
            'fuel' => $this->normFuel($this->clean($s['fuel_type_name'] ?? null)),
            'transmission' => $this->transmission($s['transmission_type_name'] ?? null),
            'condition' => 'Used',
            'color' => $this->clean($s['color_name'] ?? null),
            'body_style' => $this->normBody($this->clean($s['body_name'] ?? null)),
            'engine_cc' => isset($s['stock_cc']) && (int) $s['stock_cc'] > 0 ? (int) $s['stock_cc'] : null,
            'drive_type' => $this->normDrive($this->clean($s['stock_drive'] ?? null)),
            'doors' => isset($s['stock_door']) && (int) $s['stock_door'] > 0 ? (int) $s['stock_door'] : null,
            'steering' => $this->steering($s['stock_derivative'] ?? ''),
            'price_usd' => $price ?? 0,
            'category_id' => $this->category($s['body_name'] ?? '', $s['fuel_type_name'] ?? ''),
            'country' => 'United Arab Emirates',
            'product_link' => $card['url'] ?? (self::SITE . 'stock/' . $sid),
            'stock_ref' => $this->stockRef($s['stock_derivative'] ?? ''),
            'front_image' => $images[0],
            'images' => $images,
            'product_details' => $this->details($s),
        ];
    }

    /**
     * Real gallery list off a detail page, in order. Images may be .jpg OR .jpeg
     * (jaftim mixes both — matching only .jpg silently dropped every .jpeg car).
     * Returns [] when the page truly has no photos (caller treats that as image-less).
     */
    public function parseGalleryImages(string $detailHtml, string $stockId): array
    {
        $base = self::IMG_BASE . $stockId . '/';
        // match EVERY common image extension, not just jpg — jaftim mixes jpg / jpeg
        // and may use png / webp / avif / gif; hard-coding .jpg silently dropped cars.
        preg_match_all('#' . preg_quote($base, '#') . '([a-z0-9_-]+\.(?:jpe?g|png|webp|avif|gif|bmp))#i', $detailHtml, $m);
        $seen = [];
        $files = [];
        foreach ($m[1] as $file) {
            $file = strtolower($file);
            if (!isset($seen[$file])) {
                $seen[$file] = true;
                $files[] = $file;
            }
        }
        // front (f.*) first, then numeric ascending
        usort($files, function ($a, $b) {
            $an = preg_replace('/\.[a-z]+$/', '', $a);
            $bn = preg_replace('/\.[a-z]+$/', '', $b);
            if ($an === 'f') {
                return -1;
            }
            if ($bn === 'f') {
                return 1;
            }

            return (int) $an <=> (int) $bn;
        });

        return array_map(fn ($f) => $base . $f, $files);
    }

    private function transmission(?string $t): ?string
    {
        $t = strtoupper(trim((string) $t));

        return match ($t) {
            'AT' => 'Automatic',
            'MT' => 'Manual',
            '' => null,
            default => $t,
        };
    }

    /* Normalise jaftim's US-style facet terms onto the existing catalogue's
       (UK-style) conventions so filters don't split into duplicate entries. */
    private function normFuel(?string $f): ?string
    {
        return $f === 'Gasoline' ? 'Petrol' : $f;   // rest (Petrol/Diesel/Hybrid/Electric/LPG) already match
    }

    private function normDrive(?string $d): ?string
    {
        $u = strtoupper((string) $d);
        if ($u === '4WD' || $u === 'AWD') {
            return 'Four Wheel Drive';
        }
        if ($u === '2WD') {
            return '2WD';
        }

        return null;   // Other / 6-2 / 4-2 etc. are junk for cars
    }

    private function normBody(?string $b): ?string
    {
        return match ($b) {
            'Sedan' => 'Saloon',
            'SUV Crossover', 'Jeep' => 'SUV',
            'Station Wagon' => 'Estate',
            default => $b,
        };
    }

    private function steering(string $derivative): ?string
    {
        if (stripos($derivative, 'right') !== false) {
            return 'Right';
        }
        if (stripos($derivative, 'left') !== false) {
            return 'Left';
        }

        return null;
    }

    /** the dealer stock ref embedded in stock_derivative, e.g. "... | JFTUK0300" */
    private function stockRef(string $derivative): ?string
    {
        if (preg_match('/\b(JFT[A-Z0-9]+)\b/i', $derivative, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    private function category(string $body, string $fuel): int
    {
        $b = strtolower($body);
        $f = strtolower($fuel);
        if (str_contains($f, 'electric')) {
            return 63; // Electric Cars
        }
        if (str_contains($b, 'truck') || str_contains($b, 'pickup') || str_contains($b, 'pick up')) {
            return 4; // Trucks
        }
        if (str_contains($b, 'bus') || str_contains($b, 'coaster')) {
            return 13; // Buses
        }

        return 20; // Cars (SUV/Sedan/Hatch/Coupe/Wagon/Van/…)
    }

    /** build the products.product_details <ul> from the rich arrStock fields */
    private function details(array $s): string
    {
        $rows = [
            'Body' => $s['body_name'] ?? null,
            'Colour' => $s['color_name'] ?? null,
            'Fuel' => $s['fuel_type_name'] ?? null,
            'Transmission' => $this->transmission($s['transmission_type_name'] ?? null),
            'Engine' => !empty($s['stock_cc']) ? ($s['stock_cc'] . ' cc') : null,
            'Drive' => $s['stock_drive'] ?? null,
            'Doors' => !empty($s['stock_door']) ? $s['stock_door'] : null,
            'Steering' => $this->steering($s['stock_derivative'] ?? ''),
            'Year' => $s['reg_year'] ?? null,
        ];
        $li = '';
        foreach ($rows as $k => $v) {
            $v = $this->clean(is_scalar($v) ? (string) $v : null);
            if ($v !== null && $v !== '') {
                $li .= '<li><strong>' . htmlspecialchars($k) . ':</strong> ' . htmlspecialchars($v) . '</li>';
            }
        }
        $desc = $this->clean($s['stock_description'] ?? null);
        if ($desc) {
            $li .= '<li><strong>Description:</strong> ' . htmlspecialchars(mb_substr($desc, 0, 1200)) . '</li>';
        }

        return $li !== '' ? ('<ul>' . $li . '</ul>') : '';
    }

    private function clean(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = trim(preg_replace('/\s+/u', ' ', $s));

        return $s === '' ? null : $s;
    }
}
