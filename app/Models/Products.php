<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'mongo_id',
        'stock_code',
        'title',
        'model',
        'model_code',
        'year',
        'engine_cc',
        'mileage_km',
        'fuel',
        'transmission',
        'condition',
        'color',
        'steering',
        'seats',
        'doors',
        'drive_type',
        'axles',
        'load_capacity_kg',
        'power_hp',
        'emission_standard',
        'running_hours',
        'category_id',
        'make_id',
        'price',
        'website',
        'country',
        'body_style',
        'product_link',
        'front_image',
        'front_image_source',
        'front_image_dead_at',
        'other_images',
        'other_images_source',
        'product_details',
        'specifications',
        'shuffle_key',
    ];

    /** Give every new row a stable random shuffle_key so the default inventory
     *  order stays well-mixed (not clustered by scrape/price-band insertion). */
    protected static function booted(): void
    {
        static::creating(function (self $p) {
            if ($p->shuffle_key === null) {
                $p->shuffle_key = random_int(0, 4294967295);
            }
        });
    }

    protected $casts = [
        'price' => 'float',
        'other_images' => 'array',
        'specifications' => 'array',
    ];

    /** expose the computed price-visibility flag to the frontend as a boolean */
    protected $appends = ['show_price'];

    /** sources whose prices are trustworthy enough to display (rest show "Enquire") */
    public const PRICE_VISIBLE_SITES = ['tcv', 'suprememotors', 'electricvehicles', 'autotraderza', 'autotraderuk', 'perfectmotors', 'jaftim'];

    /**
     * Whether the card/detail page should show the numeric price. Keeps the
     * source-name logic in the backend so no website value lives in the JS.
     */
    public function getShowPriceAttribute(): bool
    {
        // show the price for EVERY car that has one (>0). Cars with no/zero price
        // still fall back to "Enquire". (Was previously restricted to a whitelist
        // of sources — the store now displays all real prices.)
        return ($this->price ?? 0) > 0;
    }

    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function make()
    {
        return $this->belongsTo(Categories::class, 'make_id');
    }

    /**
     * Full-text search on title + product_details. Boolean-mode prefix match
     * (backed by the products_search_ft index); falls back to LIKE when every
     * word is shorter than the fulltext minimum token size (3) or on sqlite.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        // Strict AND: every typed word must be present, so "audi a6" returns only
        // Audi A6 — never A8. Split on hyphens (FULLTEXT tokenises "Mercedes-Benz"
        // as two words).
        $words = collect(preg_split('/[\s\-]+/', $term))
            ->map(fn ($word) => preg_replace('/[+\-<>()~*"@]+/', '', $word))
            ->filter(fn ($word) => $word !== '');

        // FULLTEXT boolean prefix on the >=3-char tokens narrows the set fast via
        // the products_search_ft index. Short model tokens like "a6"/"q5" aren't
        // indexable, so FULLTEXT alone can't carry them — the LIKE loop below does.
        $boolean = $words->filter(fn ($word) => mb_strlen($word) >= 3)
            ->map(fn ($word) => '+'.$word.'*')->implode(' ');
        if ($boolean !== '' && in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $query->whereRaw('MATCH(title, product_details) AGAINST(? IN BOOLEAN MODE)', [$boolean]);
        }

        // Require EVERY word to appear in the title — enforces AND and captures the
        // short tokens FULLTEXT dropped.
        foreach ($words as $word) {
            $query->where('title', 'like', '%'.$word.'%');
        }

        return $query;
    }
}
