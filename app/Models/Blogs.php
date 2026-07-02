<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;

class Blogs extends Model
{
    use HasFactory;
    protected $collection = 'blogs';
    protected $fillable = [
        'title',
        'slug',
        'short_description',
        'content',
        'cover_image',
        'category',
        'tags',
        'publish_status',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}
