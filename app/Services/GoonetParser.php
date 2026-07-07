<?php

namespace App\Services;

/**
 * Parse goo-net-exchange.com (Japanese used-car export site).
 *
 * Enumeration: /php/search/summary.php?brand_cd=<CD>&offset=<20*(page-1)> lists 20
 * cars/page; each card links to a detail page /usedcars/<MAKER>/<MODEL>/<21-digit-id>/.
 * The listing carries core fields, but fuel / drive / doors / body / the full gallery
 * live ONLY on the detail page, so the full-data crawl reads each detail page.
 *
 * Detail page: server-rendered (curl.exe HTTP 200, no JS hydration). Two sources —
 * a JSON-LD Product block (name, offers.price in JPY) and clean <dl><dt>Label</dt>
 * <dd>value</dd></dl> spec rows. Prices are JPY FOB (the USD toggle is client-side JS).
 */
class GoonetParser
{
    private const SITE = 'https://www.goo-net-exchange.com';

    private const IMG_CDN = 'https://picture1.goo-net.com';

    /** JPY -> USD (goo-net's own displayed USD implies ~160.6 JPY/USD; overridable). */
    private float $jpyUsd;

    public function __construct(float $jpyUsd = 0.00622)
    {
        $this->jpyUsd = $jpyUsd;
    }

    /**
     * Detail-page URLs on a summary.php listing page, de-duplicated, in order.
     *
     * @return list<string>  absolute detail URLs
     */
    public function parseListingUrls(string $html): array
    {
        preg_match_all('#href="(/usedcars/[A-Z0-9_]+/[A-Z0-9_%-]+/\d{15,25}/)"#i', $html, $m);
        $seen = [];
        $out = [];
        foreach ($m[1] as $href) {
            if (!isset($seen[$href])) {
                $seen[$href] = true;
                $out[] = self::SITE . $href;
            }
        }

        return $out;
    }

    /** the site's global exportable total, read from any summary.php page */
    public function parseTotal(string $html): ?int
    {
        if (preg_match('#name="total"\s+value="(\d+)"#i', $html, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * brand_cd => ['name'=>, 'domestic'=>] from the homepage make-select options,
     * e.g. <option value="1010">TOYOTA(123,513)</option>. NOTE: the option count is
     * the DOMESTIC JP total, not the export-available total — the real per-brand
     * export count is read from summary.php's <input name="total"> during enumerate.
     *
     * @return array<string,array{name:string,domestic:int}>
     */
    public function parseBrands(string $homeHtml): array
    {
        preg_match_all('#<option[^>]*value="(\d{3,6})"[^>]*>\s*([A-Za-z0-9 ._/&\'.-]+?)\s*\(([\d,]+)\)\s*</option>#i', $homeHtml, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $o) {
            $cd = $o[1];
            $domestic = (int) str_replace(',', '', $o[3]);
            if (!isset($out[$cd]) || $domestic > $out[$cd]['domestic']) {
                $out[$cd] = ['name' => trim($o[2]), 'domestic' => $domestic];
            }
        }

        return $out;
    }

    /**
     * One detail page -> normalised product row. $url is the canonical detail URL.
     *
     * @return array<string,mixed>|null  null if the page has no usable car data
     */
    public function parseDetail(string $html, string $url): ?array
    {
        // URL carries maker + model reliably: /usedcars/<MAKER>/<MODEL>/<id>/
        $make = $model = '';
        $stockId = '';
        if (preg_match('#/usedcars/([A-Z0-9_]+)/([A-Z0-9_%-]+)/(\d{15,25})/#i', $url, $um)) {
            $make = $this->titleCase(str_replace('_', ' ', $um[1]));
            $model = $this->titleCase(str_replace(['_', '%20'], ' ', $um[2]));
            $stockId = $um[3];
        }

        $dd = fn (string $label) => $this->dd($html, $label);

        // price: JSON-LD offers.price (JPY integer) is authoritative
        $priceJpy = null;
        if (preg_match('#"price"\s*:\s*"?(\d+)"?#', $html, $pm)) {
            $priceJpy = (int) $pm[1];
        }

        $year = null;
        $regMonthYear = $dd('Month/Year');           // "10.2012" = MM.YYYY
        if ($regMonthYear && preg_match('#(\d{4})#', $regMonthYear, $ym)) {
            $year = (int) $ym[1];
        }

        $mileage = $this->intOf($dd('Mileage'));      // "161,000"
        $engineCc = $this->intOf($dd('Displacement')); // "4000cc" / "3000cc(D)"
        $doors = $this->intOf($dd('Doors'));           // "5D"
        $color = $this->clean($dd('Color'));
        $fuel = $this->normFuel($dd('Fuel'));
        $transmission = $this->normTransmission($dd('Transmission'));
        $drive = $this->normDrive($dd('Drive System'));
        $steering = $this->normSteering($dd('Steering'));
        $grade = $this->clean($this->jsonLdName($html, $make, $model));

        $title = trim(implode(' ', array_filter([$make, $model, $year])));
        if ($title === '') {
            $title = 'Goo-net ' . $stockId;
        }

        // goo-net's representative cover ("...00.jpg", the listing thumbnail) is
        // always a real car shot and is the true FIRST slide — the A#G# regex misses
        // it, which is why "the first image was missing" when a car had videos
        // (videos occupy the slides between the cover and the numbered photos).
        $gallery = $this->galleryImages($html, $stockId);
        $cover = $stockId !== '' ? $this->primaryImage($stockId) : null;
        $images = [];
        if ($cover !== null) {
            $images[] = $cover;
        }
        foreach ($gallery as $g) {
            if ($g !== $cover) {
                $images[] = $g;
            }
        }

        $priceUsd = $priceJpy !== null ? (int) round($priceJpy * $this->jpyUsd) : 0;

        return [
            'stock_id' => $stockId,
            'title' => mb_substr($title, 0, 255),
            'make' => $make,
            'model' => $model,
            'grade' => $grade,
            'year' => $year,
            'mileage_km' => $mileage,
            'fuel' => $fuel,
            'transmission' => $transmission,
            'condition' => 'Used',
            'color' => $color,
            'body_style' => $this->bodyFromModel($model),
            'engine_cc' => $engineCc > 0 ? $engineCc : null,
            'drive_type' => $drive,
            'doors' => $doors > 0 ? $doors : null,
            'steering' => $steering,
            'price_jpy' => $priceJpy,
            'price_usd' => $priceUsd,
            'category_id' => $this->category($model, $fuel),
            'country' => 'Japan',
            'product_link' => $url,
            'front_image' => $images[0] ?? null,
            'images' => $images,
            'product_details' => $this->details($grade, $regMonthYear, $engineCc, $fuel, $transmission, $drive, $doors, $steering, $color, $year),
        ];
    }

    /* ---------------------------------------------------------------- helpers */

    /** value of the <dd> following <dt>Label</dt> (label match is exact, case-insensitive) */
    private function dd(string $html, string $label): ?string
    {
        $l = preg_quote($label, '#');
        if (preg_match('#<dt>\s*' . $l . '\s*</dt>\s*<dd>(.*?)</dd>#is', $html, $m)) {
            return $this->clean($m[1]);
        }

        return null;
    }

    /** JSON-LD "name" minus the make/model prefix = grade (e.g. "COLOR PACKAGE") */
    private function jsonLdName(string $html, string $make, string $model): ?string
    {
        if (!preg_match('#"name"\s*:\s*"([^"]+)"#', $html, $m)) {
            return null;
        }
        $name = $m[1];
        $strip = trim($make . ' ' . str_replace('-', ' ', $model));
        $grade = trim(preg_replace('#^' . preg_quote($make, '#') . '\s+' . preg_quote(str_replace('_', ' ', $model), '#') . '#i', '', $name));

        return $grade !== '' ? $grade : null;
    }

    /**
     * Real car photos on the detail page. The gallery lives at
     * picture{N}.goo-net.com/<a>/<b>/J/<b>A<date>G<seq>.jpg (large "J" variant),
     * keyed on the dealer STOCK NUMBER (not the 21-digit URL id). We read the list
     * straight off the page and keep the large "/J/" set in filename order.
     *
     * @return list<string>
     */
    private function galleryImages(string $html, string $stockId): array
    {
        // <stock>A<date><LETTER><seq>.jpg — the sequence LETTER varies by dealer
        // template (FLEX uses 'G', Liberty uses 'W', etc.); matching only 'G' would
        // silently drop whole galleries for other dealers.
        preg_match_all('#https?://picture\d?\.goo-net\.com/[a-z0-9]+/[a-z0-9]+/J/\d+A\d+[a-z]\d+\.jpg#i', $html, $m);
        $seen = [];
        $out = [];
        foreach ($m[0] as $u) {
            if (!isset($seen[$u])) {
                $seen[$u] = true;
                $out[] = $u;
            }
        }
        sort($out);   // ...G00101, G00102, ... sorts into shooting order

        return $out;
    }

    /** primary photo derivable from the 21-digit id alone (scheme A, listing fallback) */
    private function primaryImage(string $stockId): string
    {
        $seg1 = substr($stockId, 0, 10);
        $seg2 = substr($stockId, 10, 8);

        return self::IMG_CDN . '/' . $seg1 . '/' . $seg2 . '/J/' . $stockId . '00.jpg';
    }

    private function normFuel(?string $f): ?string
    {
        $u = strtoupper(trim((string) $f));

        return match (true) {
            $u === '' => null,
            str_contains($u, 'HYBRID') => 'Hybrid',
            str_contains($u, 'GASOLINE') || str_contains($u, 'PETROL') => 'Petrol',
            str_contains($u, 'DIESEL') => 'Diesel',
            str_contains($u, 'ELECTRIC') || $u === 'EV' => 'Electric',
            default => $this->titleCase($u),
        };
    }

    private function normTransmission(?string $t): ?string
    {
        $u = strtoupper(trim((string) $t));

        return match (true) {
            $u === '' => null,
            str_starts_with($u, 'AT') || str_contains($u, 'AUTO') || $u === 'CVT' => 'Automatic',
            str_starts_with($u, 'MT') || str_contains($u, 'MANUAL') => 'Manual',
            default => $this->titleCase($u),
        };
    }

    private function normDrive(?string $d): ?string
    {
        $u = strtoupper(trim((string) $d));
        if ($u === '4WD' || $u === 'AWD') {
            return 'Four Wheel Drive';
        }
        if ($u === '2WD' || $u === 'FF' || $u === 'FR') {
            return '2WD';
        }

        return null;
    }

    private function normSteering(?string $s): ?string
    {
        $u = strtolower(trim((string) $s));
        if (str_contains($u, 'right')) {
            return 'Right';
        }
        if (str_contains($u, 'left')) {
            return 'Left';
        }

        return null;
    }

    private function bodyFromModel(string $model): ?string
    {
        $m = strtolower($model);

        return match (true) {
            str_contains($m, 'truck') || str_contains($m, 'dump') => 'Pickup',
            str_contains($m, 'van') || str_contains($m, 'hiace') || str_contains($m, 'caravan') => 'Van',
            str_contains($m, 'bus') || str_contains($m, 'coaster') => 'Bus',
            default => null,   // goo-net has no explicit body field; left for facets to infer
        };
    }

    private function category(string $model, ?string $fuel): int
    {
        $m = strtolower($model);
        if ($fuel && str_contains(strtolower($fuel), 'electric')) {
            return 63;
        }
        if (str_contains($m, 'truck') || str_contains($m, 'dump')) {
            return 4;
        }
        if (str_contains($m, 'bus') || str_contains($m, 'coaster')) {
            return 13;
        }

        return 20;
    }

    private function details(?string $grade, ?string $reg, ?int $cc, ?string $fuel, ?string $trans, ?string $drive, ?int $doors, ?string $steering, ?string $color, ?int $year): string
    {
        $rows = [
            'Grade' => $grade,
            'Reg. (M/Y)' => $reg,
            'Year' => $year,
            'Engine' => $cc ? ($cc . ' cc') : null,
            'Fuel' => $fuel,
            'Transmission' => $trans,
            'Drive' => $drive,
            'Doors' => $doors,
            'Steering' => $steering,
            'Colour' => $color,
        ];
        $li = '';
        foreach ($rows as $k => $v) {
            $v = $this->clean(is_scalar($v) ? (string) $v : null);
            if ($v !== null && $v !== '') {
                $li .= '<li><strong>' . htmlspecialchars($k) . ':</strong> ' . htmlspecialchars($v) . '</li>';
            }
        }

        return $li !== '' ? ('<ul>' . $li . '</ul>') : '';
    }

    private function intOf(?string $s): int
    {
        return (int) preg_replace('#[^0-9]#', '', (string) $s);
    }

    /** Title-case, but keep short tokens (<=3 chars) uppercase so BMW/GMC/FJ/GT survive. */
    private function titleCase(string $s): string
    {
        return preg_replace_callback('#[A-Za-z0-9]+#u', function ($m) {
            $w = $m[0];

            return strlen($w) <= 3 ? strtoupper($w) : ucfirst(strtolower($w));
        }, trim($s));
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
