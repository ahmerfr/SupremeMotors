<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if ($user && in_array($user->role, $roles, true)) {
            return $next($request);
        }

        return redirect('/')->with('message', 'You do not have permission to access this page.');
    }
}
