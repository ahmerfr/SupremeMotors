<?php

namespace App\Support;

class ProductDetailsParser
{
    private const JUNK = ['-', '', 'n/a', 'na', 'unspecified', 'confirm with the seller'];

    private const FIELDS = [
        'model', 'model_code', 'year', 'engine_cc', 'mileage_km', 'fuel',
        'transmission', 'condition', 'color', 'steering', 'seats', 'doors', 'drive_type',
    ];

    /**
     * Extract structured attributes from `<strong>Key:</strong> value` HTML.
     * Returns all 12 keys; missing/junk values are null.
     */
    public static function parse(?string $html): array
    {
        $out = array_fill_keys(self::FIELDS, null);
        if ($html === null || $html === '') {
            return $out;
        }

        $raw = [];
        // Format 1 (scraped listings): <li><strong>Key:</strong> value</li>
        if (preg_match_all('/<strong>([^<:]{1,40}):?<\/strong>:?\s*([^<]{0,120})/u', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $key = mb_strtolower(trim($pair[1]));
                if (! isset($raw[$key])) {
                    $raw[$key] = self::clean($pair[2]);
                }
            }
        }
        // Format 2 (site's own products): <p>Key&nbsp;:&nbsp;Value</p> or
        // tab-separated <p>Key\tValue</p>; keys may carry a trailing period.
        $decoded = str_replace("\u{00A0}", ' ', html_entity_decode($html));
        if (preg_match_all('/<p>\s*([^<:\t]{1,40}?)\s*[:\t]\s*([^<]{0,120}?)\s*<\/p>/u', $decoded, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $key = rtrim(mb_strtolower(trim($pair[1])), '.');
                if (! isset($raw[$key])) {
                    $raw[$key] = self::clean(rtrim($pair[2], "\t "));
                }
            }
        }
        if ($raw === []) {
            return $out;
        }

        // Second value = column length; real data overflows (e.g. condition
        // "Used (accident Not Repaired)"), so clamp to schema.
        $out['model'] = self::clamp($raw['model'] ?? null, 100);
        $out['model_code'] = self::clamp($raw['model code'] ?? $raw['chassis no'] ?? null, 60);
        $out['year'] = self::year($raw);
        $out['engine_cc'] = self::engineCc(
            $raw['engine capacity (displacement)'] ?? $raw['engine capacity'] ?? $raw['displacement']
                ?? $raw['engine size'] ?? $raw['engine'] ?? null
        );
        $out['mileage_km'] = self::mileageKm($raw['mileage'] ?? null);
        $out['fuel'] = self::fuel($raw['fuel'] ?? $raw['fuel type'] ?? null);
        $out['transmission'] = self::transmission($raw['transmission'] ?? null);
        $out['condition'] = self::clamp(self::title($raw['condition'] ?? null), 40);
        $out['color'] = self::clamp(self::title($raw['exterior color'] ?? $raw['colour'] ?? $raw['color'] ?? $raw['ext. color'] ?? $raw['ext color'] ?? null), 40);
        $out['steering'] = self::steering($raw['steering'] ?? null);
        $out['seats'] = self::seats($raw['number of seats'] ?? $raw['seats'] ?? null);
        $out['doors'] = self::doors($raw['door'] ?? $raw['doors'] ?? null);
        $out['drive_type'] = self::driveType($raw['drive type'] ?? $raw['drive system'] ?? null);

        return $out;
    }

    private static function clamp(?string $value, int $max): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $max);
    }

    private static function clean(string $value): ?string
    {
        $value = trim(html_entity_decode($value));

        return in_array(mb_strtolower($value), self::JUNK, true) ? null : $value;
    }

    private static function title(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = ucwords(mb_strtolower($value));

        // "4wheel" -> "4Wheel": ucwords skips letters right after digits.
        return preg_replace_callback('/\b(\d+)([a-z])/', fn ($m) => $m[1].strtoupper($m[2]), $t);
    }

    private static function year(array $raw): ?int
    {
        foreach (['registration year / month', 'year of manufacture', 'first registration', 'year'] as $key) {
            $value = $raw[$key] ?? null;
            if ($value !== null && preg_match('/\b(\d{4})\b/', $value, $m)) {
                $year = (int) $m[1];
                if ($year >= 1950 && $year <= 2027) {
                    return $year;
                }
            }
        }

        return null;
    }

    private static function engineCc(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        // Litre form ("2.0L") — only when no cc digits present.
        if (! str_contains(mb_strtolower($value), 'cc')
            && preg_match('/(\d+(?:\.\d+)?)\s*l\b/i', $value, $m)) {
            return (int) round(((float) $m[1]) * 1000);
        }
        $digits = preg_replace('/[^0-9]/', '', $value);
        if ($digits === '') {
            return null;
        }
        $n = (int) $digits;

        return ($n >= 50 && $n <= 30_000) ? $n : null;
    }

    private static function mileageKm(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', $value);
        if ($digits === '') {
            return null;
        }
        $n = (int) $digits;
        if (str_contains(mb_strtolower($value), 'mile')) {
            $n = (int) round($n * 1.609);
        }

        // Scraped junk goes past INT range; no real vehicle exceeds 2M km.
        return $n > 2_000_000 ? null : $n;
    }

    /**
     * Scraped fuel values are messy ("Gasoline/petrol", "Electro", "Gas") —
     * canonicalize to a fixed filterable set; unmappable -> NULL (the raw
     * value is still in product_details).
     */
    private static function fuel(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = mb_strtolower($value);

        return match (true) {
            str_contains($v, 'hybrid'),
            str_contains($v, 'diesel') && str_contains($v, 'electro') => 'Hybrid',
            str_contains($v, 'diesel') => 'Diesel',
            str_contains($v, 'gasoline'), str_contains($v, 'petrol'), $v === 'gas' => 'Petrol',
            str_contains($v, 'electro'), str_contains($v, 'electric'), $v === 'ev' => 'Electric',
            str_contains($v, 'lpg') => 'LPG',
            str_contains($v, 'cng'), str_contains($v, 'natural gas') => 'CNG',
            default => null,
        };
    }

    /**
     * Truck listings put gearbox model codes here ("Hw19710, 10 Forwards…");
     * those are manual boxes. Canonical set or NULL.
     */
    private static function transmission(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = mb_strtolower($value);

        return match (true) {
            str_contains($v, 'cvt') => 'CVT',
            str_contains($v, 'semi') => 'Semi-Automatic',
            str_contains($v, 'auto'), $v === 'at' => 'Automatic',
            str_contains($v, 'manual'), str_contains($v, 'forward'), $v === 'mt',
            preg_match('/\bhw1\d{4}/', $v) === 1, str_contains($v, 'jsd') => 'Manual',
            default => null,
        };
    }

    private static function steering(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = mb_strtolower($value);
        $left = str_contains($v, 'left');
        $right = str_contains($v, 'right');

        return match (true) {
            $left && $right => null,
            $right => 'Right',
            $left => 'Left',
            default => null,
        };
    }

    private static function driveType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = mb_strtolower($value);

        return match (true) {
            str_contains($v, '4wheel'), str_contains($v, '4wd'),
            str_contains($v, '4x4'), str_contains($v, '4 wheel') => '4WD',
            str_contains($v, 'awd'), str_contains($v, 'all') => 'AWD',
            str_contains($v, '2wheel'), str_contains($v, '2wd'),
            str_contains($v, '4x2'), str_contains($v, '2 wheel') => '2WD',
            default => self::clamp(self::title($value), 30), // axle configs like "6*4" stay
        };
    }

    /** "5", "4D" -> int; "0", "6mm", junk -> null. */
    private static function doors(?string $value): ?int
    {
        if ($value === null || ! preg_match('/^(\d)\s*d?$/i', trim($value), $m)) {
            return null;
        }
        $n = (int) $m[1];

        return $n >= 1 ? $n : null;
    }

    private static function seats(?string $value): ?int
    {
        if ($value === null || ! preg_match('/\d+/', $value, $m)) {
            return null;
        }
        $n = (int) $m[0];

        return ($n >= 1 && $n <= 99) ? $n : null;
    }
}
