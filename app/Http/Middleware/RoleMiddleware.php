<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    use ApiResponse;

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth('api')->user();

        if (! $user) {
            return $this->error('Usuario no autenticado.', 401);
        }

        if (! in_array($user->rol, $roles, true)) {
            return $this->error('No tiene permisos para realizar esta acción.', 403);
        }

        return $next($request);
    }
}
