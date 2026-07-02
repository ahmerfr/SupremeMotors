<?php

namespace App\Http\Controllers;
use App\Models\User;
abstract class Controller {
    //
    protected $user_id;
    protected $user;

    public function __construct() {
        if(auth()->user()){
            $this->user_id = auth()->id();
            $this->user = auth()->user();
        }
        
    }
}