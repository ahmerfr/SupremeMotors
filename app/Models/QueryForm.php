<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class QueryForm extends Model {
    use HasFactory;

    protected $collection = 'queryform_entry'; 
}
