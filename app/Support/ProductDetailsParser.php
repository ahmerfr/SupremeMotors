<?php

namespace App\Support;

class ProductDetailsParser
{
    private const JUNK = ['-', '', 'n/a', 'na', 'unspecified', 'confirm with the seller'];

    private const FIELDS = [
        'model', 'model_code', 'year', 'engine_cc', 'mileage_km', 'fuel',
        'transmission', 'condition', 'color', 'steering', 'seats', 'drive_type',
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
        if (preg_match_all('/<strong>([^<:]{1,40}):?<\/strong>:?\s*([^<]{0,120})/u', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $key = mb_strtolower(trim($pair[1]));
                if (! isset($raw[$key])) {
                    $raw[$key] = self::clean($pair[2]);
                }
            }
        }
        if ($raw === []) {
            return $out;
        }

        // Second value = column length; real data overflows (e.g. condition
        // "Used (accident Not Repaired)"), so clamp to schema.
        $out['model'] = self::clamp($raw['model'] ?? null, 100);
        $out['model_code'] = self::clamp($raw['model code'] ?? null, 60);
        $out['year'] = self::year($raw);
        $out['engine_cc'] = self::engineCc($raw['engine capacity (displacement)'] ?? $raw['engine capacity'] ?? null);
        $out['mileage_km'] = self::mileageKm($raw['mileage'] ?? null);
        $out['fuel'] = self::clamp(self::title($raw['fuel'] ?? null), 30);
        $out['transmission'] = self::clamp(self::title($raw['transmission'] ?? null), 30);
        $out['condition'] = self::clamp(self::title($raw['condition'] ?? null), 40);
        $out['color'] = self::clamp(self::title($raw['exterior color'] ?? $raw['colour'] ?? $raw['color'] ?? null), 40);
        $out['steering'] = self::clamp(self::title($raw['steering'] ?? null), 10);
        $out['seats'] = self::seats($raw['number of seats'] ?? null);
        $out['drive_type'] = self::clamp(self::title($raw['drive type'] ?? null), 30);

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
        foreach (['registration year / month', 'year of manufacture', 'first registration'] as $key) {
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

        return $digits === '' ? null : (int) $digits;
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

        return $n;
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
