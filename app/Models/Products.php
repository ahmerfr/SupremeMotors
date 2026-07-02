<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;

class Products extends Model
{
    use HasFactory;
    protected $collection = 'products';
    protected $fillable = [
        'title',
        'category_id',
        'make_id',
        'price',
        'website',
        'country',
        'front_image',
        'other_images',
        'product_details'
    ];
    public function category()
    {
        return $this->hasOne(Categories::class, '_id', 'category_id');
    }
    public function make()
    {
        return $this->hasOne(Categories::class, '_id', 'make_id');
    }
}
