<?php
// app/Http/Middleware/AdminMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role === 'admin') {
                return $next($request);
            } elseif ($user->role === 'user') {
                return redirect('/')->with('message', 'You do not have permission to access this page.');
            }
        }
        return redirect('/');
    }
}
