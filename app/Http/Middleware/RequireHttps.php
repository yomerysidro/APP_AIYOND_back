<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('auth_tokens.require_https') && app()->environment('production') && ! $request->secure()) {
            abort(403, 'HTTPS es obligatorio.');
        }

        return $next($request);
    }
}
