<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'mongo_id',
        'cat_title',
        'description',
        'image',
        'type',
        'parent_id',
    ];

    public function cat_products()
    {
        return $this->hasMany(Products::class, 'category_id');
    }

    public function make_products()
    {
        return $this->hasMany(Products::class, 'make_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Expand category ids to include their direct children, so filtering by
     * a top-level category also matches products filed under subcategories.
     */
    public static function expandWithChildren(array $ids): array
    {
        $children = static::whereIn('parent_id', $ids)->pluck('id')->all();

        return array_values(array_unique(array_merge(array_map('intval', $ids), $children)));
    }
}
