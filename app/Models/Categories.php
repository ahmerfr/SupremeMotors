<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Categories extends Model
{
    use HasFactory;

    
    protected $collection = 'categories'; // If using MongoDB

    // protected $fillable = [
    //     'cat_title',
    //     'description',
    //     'image',
    //     'type',
    // ];
    public function cat_products()
    {
        return $this->hasMany(Products::class, 'category_id', '_id');
    }

    public function make_products()
    {
        return $this->hasMany(Products::class, 'make_id', '_id');
    }
}
