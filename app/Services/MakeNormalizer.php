<?php

namespace App\Services;

/**
 * Canonicalises a make name so spelling variants collapse to one make instead
 * of splitting a brand's inventory across duplicates (e.g. "Mercedes-Benz",
 * "Mercedes-AMG" and "Mercedes-Maybach" all resolve to "Mercedes Benz").
 *
 * Unknown makes pass through unchanged (trimmed), so a genuinely new brand
 * (Jaguar, Mitsubishi, Chery, ...) still auto-creates as itself. Case is
 * already handled by MySQL's case-insensitive collation, so this only fixes
 * real spelling differences (hyphenation, sub-brands, extra words).
 */
class MakeNormalizer
{
    /** lowercased variant (hyphens and spaces both normalised to space) => canonical */
    private const ALIASES = [
        'mercedes' => 'Mercedes Benz',
        'mercedes benz' => 'Mercedes Benz',
        'mercedes amg' => 'Mercedes Benz',
        'mercedes maybach' => 'Mercedes Benz',
        'amg' => 'Mercedes Benz',
        'maybach' => 'Mercedes Benz',
        'alfa' => 'Alfa Romeo',
        'kia motors' => 'Kia',
        'vw' => 'Volkswagen',
        'range rover' => 'Land Rover',
        'mini cooper' => 'Mini',
        'chevy' => 'Chevrolet',
        'landrover' => 'Land Rover',
    ];

    public function canonical(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }
        // key: lowercase, hyphens/underscores -> space, collapse whitespace
        $key = preg_replace('/\s+/', ' ', strtolower(str_replace(['-', '_'], ' ', $trimmed)));

        return self::ALIASES[$key] ?? $trimmed;
    }
}
