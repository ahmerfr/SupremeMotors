<?php

namespace App\Http\Controllers;
use App\Models\User;
abstract class Controller {
    //
    protected $user_id;
    protected $user;

    public function __construct() {
        if(auth()->user()){
            $url = \Request::getRequestUri();   
            $this->user_id = auth()->user()->_id;
            $this->user = User::where('_id', $this->user_id)->first();
        }
        
    }
}