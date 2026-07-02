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
    ];

    public function cat_products()
    {
        return $this->hasMany(Products::class, 'category_id');
    }

    public function make_products()
    {
        return $this->hasMany(Products::class, 'make_id');
    }
}
