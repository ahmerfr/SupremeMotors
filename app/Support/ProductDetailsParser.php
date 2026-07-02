<?php

namespace App\Support;

class ProductDetailsParser
{
    private const JUNK = ['-', '', 'n/a', 'na', 'unspecified', 'confirm with the seller'];

    private const FIELDS = [
        'model', 'model_code', 'year', 'engine_cc', 'mileage_km', 'fuel',
        'transmission', 'condition', 'color', 'steering', 'seats', 'doors', 'drive_type',
        'axles', 'load_capacity_kg', 'power_hp', 'emission_standard', 'running_hours',
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
        $out['transmission'] = self::transmission($raw['transmission'] ?? $raw['transmission type'] ?? null);
        $out['condition'] = self::condition($raw['condition'] ?? null);
        $out['color'] = self::clamp(self::title($raw['exterior color'] ?? $raw['colour'] ?? $raw['color'] ?? $raw['ext. color'] ?? $raw['ext color'] ?? null), 40);
        $out['steering'] = self::steering($raw['steering'] ?? null);
        $out['seats'] = self::seats($raw['number of seats'] ?? $raw['seats'] ?? null);
        $out['doors'] = self::doors($raw['door'] ?? $raw['doors'] ?? null);
        $out['drive_type'] = self::driveType(
            $raw['drive type'] ?? $raw['drive system'] ?? $raw['axle configuration']
                ?? $raw['drive wheel'] ?? $raw['drive mode'] ?? null
        );
        $out['axles'] = self::smallInt($raw['number of axles'] ?? null, 1, 9);
        $out['load_capacity_kg'] = self::loadCapacityKg(
            $raw['load capacity'] ?? $raw['payload'] ?? $raw['loading capacity'] ?? null
        );
        $out['power_hp'] = self::powerHp(
            $raw['power'] ?? $raw['horsepower'] ?? $raw['maximum horsepower'] ?? $raw['horse power'] ?? null
        );
        $out['emission_standard'] = self::emissionStandard(
            $raw['emission standard'] ?? $raw['euro'] ?? $raw['emission'] ?? null
        );
        $out['running_hours'] = self::boundedDigits($raw['running hours'] ?? null, 1, 200_000);

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

    /** "New/used", "Both New And Used", "According to customer's choice" say nothing — NULL. */
    private static function condition(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = mb_strtolower($value);
        if (str_contains($v, '/') || str_contains($v, 'both') || str_contains($v, 'according') || str_contains($v, 'choice')) {
            return null;
        }

        return self::clamp(self::title($value), 40);
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

        // Steering-side text leaks into this key on some sources.
        if (str_contains($v, 'hand drive') || str_contains($v, 'lhd') || str_contains($v, 'rhd')) {
            return null;
        }
        // Axle configs: "6X4" / "6×4" / "6*4" -> "6x4".
        if (preg_match('/^(\d)\s*[x×*]\s*(\d)$/u', trim($v), $m)) {
            return $m[1] === '4' && $m[2] === '4' ? '4WD'
                : ($m[1] === '4' && $m[2] === '2' ? '2WD' : "{$m[1]}x{$m[2]}");
        }

        return match (true) {
            str_contains($v, '4wheel'), str_contains($v, '4wd'), str_contains($v, '4 wheel') => '4WD',
            str_contains($v, 'awd'), str_contains($v, 'all') => 'AWD',
            str_contains($v, 'front') => 'FWD',
            str_contains($v, 'rear') => 'RWD',
            str_contains($v, '2wheel'), str_contains($v, '2wd'), str_contains($v, '2 wheel') => '2WD',
            default => self::clamp(self::title($value), 30),
        };
    }

    private static function smallInt(?string $value, int $min, int $max): ?int
    {
        if ($value === null || ! preg_match('/\d+/', $value, $m)) {
            return null;
        }
        $n = (int) $m[0];

        return ($n >= $min && $n <= $max) ? $n : null;
    }

    private static function boundedDigits(?string $value, int $min, int $max): ?int
    {
        if ($value === null || ! preg_match('/[\d,.]+/', $value, $m)) {
            return null;
        }
        $n = (int) preg_replace('/[^0-9]/', '', $m[0]);

        return ($n >= $min && $n <= $max) ? $n : null;
    }

    /** "3,000 kg", "40~120 Tons", "120KGS" -> kg; first number wins, tons x1000. */
    private static function loadCapacityKg(?string $value): ?int
    {
        if ($value === null || ! preg_match('/([\d,.]+)/', $value, $m)) {
            return null;
        }
        $n = (float) str_replace(',', '', $m[1]);
        if (preg_match('/ton/i', $value)) {
            $n *= 1000;
        }
        $n = (int) round($n);

        return ($n >= 1 && $n <= 500_000) ? $n : null;
    }

    /** "110 kW (150 HP)" -> 150; "110 kW" -> 148; "351-450hp" -> 351. */
    private static function powerHp(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $clean = str_replace(',', '', $value);
        if (preg_match('/([\d.]+)\s*[-~]\s*[\d.]+\s*hp/i', $clean, $m)) {
            $n = (int) round((float) $m[1]); // "351-450hp" -> range start
        } elseif (preg_match('/([\d.]+)\s*hp/i', $clean, $m)) {
            $n = (int) round((float) $m[1]);
        } elseif (preg_match('/([\d.]+)\s*kw/i', $clean, $m)) {
            $n = (int) round(((float) $m[1]) * 1.34102);
        } elseif (preg_match('/([\d.]+)/', $clean, $m)) {
            $n = (int) round((float) $m[1]);
        } else {
            return null;
        }

        return ($n >= 10 && $n <= 5_000) ? $n : null;
    }

    /** "Euro 3" / "Euro II" -> "Euro N"; multi-value junk ("euro 2/3/4/5") -> null. */
    private static function emissionStandard(?string $value): ?string
    {
        if ($value === null || str_contains($value, '/')) {
            return null;
        }
        $roman = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6];
        if (preg_match('/euro\s*([1-6]|i{1,3}v?|vi?)\b/i', $value, $m)) {
            $token = mb_strtolower($m[1]);
            $n = ctype_digit($token) ? (int) $token : ($roman[$token] ?? null);

            return $n === null ? null : "Euro {$n}";
        }

        return null;
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
