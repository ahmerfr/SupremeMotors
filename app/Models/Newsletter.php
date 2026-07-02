<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Newsletter extends Model {
    use HasFactory;

    protected $collection = 'newsletter_entry'; 
}
