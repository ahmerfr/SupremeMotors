<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class ContactForm extends Model {
    use HasFactory;

    protected $collection = 'contact_form'; 
}
