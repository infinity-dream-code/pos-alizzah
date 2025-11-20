<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return redirect('/');
        }

        if (!in_array(Auth::user()->role, $roles)) {
            if (Auth::user()->role === 'admin') {
                return redirect('/admin');
            }

            if (Auth::user()->role === 'kasir') {
                return redirect('/kasir');
            }

            return redirect('/');
        }

        return $next($request);
    }
}
