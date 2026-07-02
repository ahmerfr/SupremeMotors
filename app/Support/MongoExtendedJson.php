<?php

namespace App\Support;

use Carbon\Carbon;

class MongoExtendedJson
{
    /**
     * Recursively flatten MongoDB Extended JSON (v2) wrappers into plain scalars.
     */
    public static function normalize(array $doc): array
    {
        $out = [];
        foreach ($doc as $key => $value) {
            $out[$key] = is_array($value) ? self::normalizeValue($value) : $value;
        }

        return $out;
    }

    private static function normalizeValue(array $value): mixed
    {
        if (array_key_exists('$oid', $value)) {
            return $value['$oid'];
        }
        if (array_key_exists('$date', $value)) {
            $date = $value['$date'];
            if (is_array($date) && isset($date['$numberLong'])) {
                return Carbon::createFromTimestampMs((int) $date['$numberLong'], 'UTC')
                    ->format('Y-m-d H:i:s');
            }

            return Carbon::parse($date)->utc()->format('Y-m-d H:i:s');
        }
        if (array_key_exists('$numberInt', $value) || array_key_exists('$numberLong', $value)) {
            return (int) ($value['$numberInt'] ?? $value['$numberLong']);
        }
        if (array_key_exists('$numberDouble', $value) || array_key_exists('$numberDecimal', $value)) {
            return (float) ($value['$numberDouble'] ?? $value['$numberDecimal']);
        }

        return self::normalize($value);
    }

    /**
     * "24,920" / "1234 USD" / "" / null / 52000.5 -> float.
     */
    public static function parsePrice(mixed $raw): float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $clean = preg_replace('/[^0-9.]/', '', (string) $raw);

        return $clean === '' ? 0.0 : (float) $clean;
    }
}
