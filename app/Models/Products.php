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
        'title',
        'category_id',
        'make_id',
        'price',
        'website',
        'country',
        'body_style',
        'product_link',
        'front_image',
        'other_images',
        'product_details',
    ];

    protected $casts = [
        'price' => 'float',
        'other_images' => 'array',
    ];

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
        $boolean = collect(preg_split('/\s+/', $term))
            ->map(fn ($word) => preg_replace('/[+\-<>()~*"@]+/', '', $word))
            ->filter(fn ($word) => mb_strlen($word) >= 3)
            ->map(fn ($word) => '+'.$word.'*')
            ->implode(' ');

        if ($boolean === '' || ! in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('product_details', 'like', "%{$term}%");
            });
        }

        return $query->whereRaw('MATCH(title, product_details) AGAINST(? IN BOOLEAN MODE)', [$boolean]);
    }
}
