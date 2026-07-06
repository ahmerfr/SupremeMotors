<?php

namespace App\Services;

/**
 * Parse an autoline.info advert detail page into a normalised row.
 *
 * WHY TWO SOURCES: the page ships a JSON-LD ["Product","Vehicle"] block (clean
 * name / price / currency / brand / full image list / condition) AND a visible
 * spec table (`<div class="item"><span class="field">K:</span><span
 * class="value">V</span></div>`) that carries the rich technical detail (year,
 * mileage, axles, fuel, emission class, ...). We read the JSON-LD for the
 * trustworthy core fields and the spec table for everything else, then map the
 * known spec keys onto the products columns and keep the full list as the
 * `product_details` <ul> HTML — the exact shape the existing autoline rows use.
 *
 * The stable Autoline advert id is the trailing `--<digits>` of the advert URL
 * (identical to the `--<digits>.jpg` suffix on every image); it is the dedup key
 * against the rows already in the database.
 */
class AutolineDetailParser
{
    /**
     * @return array<string,mixed>|null  null when the page is not a parseable advert
     */
    public function parse(string $html, string $url): ?array
    {
        $ld = $this->productJsonLd($html);

        // VEHICLES ONLY: autoline.info mixes a huge spare-parts catalog into the
        // same sitemaps. A vehicle advert's JSON-LD @type is ["Product","Vehicle"];
        // a spare part is just "Product" (breadcrumb "Spare parts"). SupremeMotors
        // is a vehicle marketplace and every existing autoline row is a vehicle, so
        // reject anything that is not a Vehicle.
        if (!$this->isVehicle($ld)) {
            return null;
        }

        $specs = $this->specPairs($html);

        $listingId = $this->listingId($url);
        $title = $this->clean($ld['name'] ?? '') ?: $this->firstSpecTitle($html);
        if ($title === '' && $listingId === '') {
            return null;
        }

        $images = $this->images($ld);
        [$priceEur, $currency] = $this->price($ld);

        $row = [
            'listing_id' => $listingId,
            'title' => $title,
            'brand' => $this->clean(is_array($ld['brand'] ?? null) ? ($ld['brand']['name'] ?? '') : ''),
            'price_eur' => $priceEur,
            'currency' => $currency,
            'condition' => $this->condition($ld, $specs),
            'front_image' => $images[0] ?? null,
            'images' => $images,
            'product_link' => $url,
            'specs' => $specs,
            'product_details' => $this->specsToHtml($specs),
        ];

        // map the known spec keys onto real columns
        return $row + $this->mapSpecColumns($specs);
    }

    /** trailing --<digits> of the advert URL == the stable Autoline advert id */
    public function listingId(string $url): string
    {
        if (preg_match('/--(\d{6,})(?:[?#].*)?$/', $url, $m)) {
            return $m[1];
        }

        return '';
    }

    /** the --<digits>.jpg suffix on any linemedia image == the same advert id */
    public function listingIdFromImage(string $imageUrl): string
    {
        if (preg_match('/--(\d{6,})\.[a-z]+/i', $imageUrl, $m)) {
            return $m[1];
        }

        return '';
    }

    /** true only when the advert's JSON-LD @type marks it a Vehicle (not a part) */
    private function isVehicle(array $ld): bool
    {
        $type = $ld['@type'] ?? '';
        $types = is_array($type) ? $type : [$type];

        return in_array('Vehicle', $types, true);
    }

    /** pick the ["Product","Vehicle"] JSON-LD block */
    private function productJsonLd(string $html): array
    {
        if (!preg_match_all('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $html, $m)) {
            return [];
        }
        foreach ($m[1] as $blob) {
            $data = json_decode(trim($blob), true);
            if (!is_array($data)) {
                continue;
            }
            $type = $data['@type'] ?? '';
            $types = is_array($type) ? $type : [$type];
            if (in_array('Product', $types, true) || in_array('Vehicle', $types, true)) {
                return $data;
            }
        }

        return [];
    }

    /** @return list<string> deduped, https, _big image urls in page order */
    private function images(array $ld): array
    {
        $imgs = $ld['image'] ?? [];
        if (is_string($imgs)) {
            $imgs = [$imgs];
        }
        $out = [];
        foreach ((array) $imgs as $u) {
            if (!is_string($u) || $u === '') {
                continue;
            }
            $u = trim($u);
            if (!in_array($u, $out, true)) {
                $out[] = $u;
            }
        }

        return $out;
    }

    /** @return array{0:float|null,1:string} price + currency from offers */
    private function price(array $ld): array
    {
        $offers = $ld['offers'] ?? null;
        if (is_array($offers) && isset($offers[0])) {
            $offers = $offers[0];
        }
        if (!is_array($offers)) {
            return [null, 'EUR'];
        }
        $p = $offers['price'] ?? null;
        $price = is_numeric($p) ? (float) $p : null;
        $cur = is_string($offers['priceCurrency'] ?? null) ? $offers['priceCurrency'] : 'EUR';

        return [$price, $cur];
    }

    private function condition(array $ld, array $specs): string
    {
        foreach ($specs as $pair) {
            if (stripos($pair[0], 'condition') !== false) {
                return ucfirst(strtolower($this->clean($pair[1])));
            }
        }
        $offers = $ld['offers'] ?? [];
        $ic = is_array($offers) ? ($offers['itemCondition'] ?? '') : '';
        if (stripos((string) $ic, 'Used') !== false) {
            return 'Used';
        }
        if (stripos((string) $ic, 'New') !== false) {
            return 'New';
        }

        return '';
    }

    /**
     * Every visible spec row, in page order.
     *
     * @return list<array{0:string,1:string}>  [label, value]
     */
    private function specPairs(string $html): array
    {
        $out = [];
        $re = '#<div class="item">\s*<span class="field">(.*?)</span>\s*<span class="value">(.*?)</span>\s*</div>#is';
        if (preg_match_all($re, $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $k = rtrim($this->clean($row[1]), ': ');
                $v = $this->clean($row[2]);
                if ($k !== '' && $v !== '') {
                    $out[] = [$k, $v];
                }
            }
        }

        return $out;
    }

    /** first spec value as a fallback title */
    private function firstSpecTitle(string $html): string
    {
        if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $m)) {
            return $this->clean($m[1]);
        }

        return '';
    }

    /** the products.product_details <ul> shape the existing autoline rows use */
    private function specsToHtml(array $specs): string
    {
        if ($specs === []) {
            return '';
        }
        $li = '';
        foreach ($specs as [$k, $v]) {
            $li .= '<li><strong>' . htmlspecialchars($k, ENT_QUOTES) . ':</strong> '
                . htmlspecialchars($v, ENT_QUOTES) . '</li>';
        }

        return '<ul>' . $li . '</ul>';
    }

    /** map known Autoline spec labels onto real products columns */
    private function mapSpecColumns(array $specs): array
    {
        $col = [];
        foreach ($specs as [$k, $v]) {
            $kl = strtolower($k);
            if (str_contains($kl, 'year of manufacture') && preg_match('/(\d{4})/', $v, $mm)) {
                $col['year'] = (int) $mm[1];
            } elseif ($kl === 'mileage' && preg_match('/([\d,\. ]+)/', $v, $mm)) {
                $col['mileage_km'] = (int) preg_replace('/\D/', '', $mm[1]);
            } elseif ($kl === 'fuel') {
                $col['fuel'] = ucfirst($v);
            } elseif (str_contains($kl, 'gearbox') || str_contains($kl, 'transmission')) {
                $col['transmission'] = $v;
            } elseif (str_contains($kl, 'colour') || str_contains($kl, 'color')) {
                $col['color'] = ucfirst($v);
            } elseif (str_contains($kl, 'number of axles') && preg_match('/(\d+)/', $v, $mm)) {
                $col['axles'] = (int) $mm[1];
            } elseif (str_contains($kl, 'axle configuration') || $kl === 'wheel formula') {
                $col['drive_type'] = $v;
            } elseif (str_contains($kl, 'euro') || str_contains($kl, 'emission')) {
                $col['emission_standard'] = $v;
            } elseif (str_contains($kl, 'load capacity') && preg_match('/([\d,\. ]+)/', $v, $mm)) {
                $col['load_capacity_kg'] = (int) preg_replace('/\D/', '', $mm[1]);
            } elseif ((str_contains($kl, 'power') || str_contains($kl, 'engine power')) && preg_match('/(\d+)\s*hp/i', $v, $mm)) {
                $col['power_hp'] = (int) $mm[1];
            } elseif (str_contains($kl, 'running hours') || str_contains($kl, 'operating hours')) {
                if (preg_match('/([\d,\. ]+)/', $v, $mm)) {
                    $col['running_hours'] = (int) preg_replace('/\D/', '', $mm[1]);
                }
            } elseif (str_contains($kl, 'number of seats') || $kl === 'seats') {
                if (preg_match('/(\d+)/', $v, $mm)) {
                    $col['seats'] = (int) $mm[1];
                }
            }
        }

        return $col;
    }

    /** strip tags, decode entities, collapse whitespace */
    private function clean(string $s): string
    {
        $s = preg_replace('/<[^>]*>/', ' ', $s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }
}
