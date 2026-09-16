<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleMiddleware;
use App\Models\User\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    public function test_missing_user_is_unauthorized_even_without_auth_middleware(): void
    {
        $response = (new RoleMiddleware)->handle(Request::create('/'), fn () => new Response('', 204), 'admin');
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_roles_are_checked_strictly_and_support_multiple_allowed_values(): void
    {
        $this->actingAs(new User(['rol' => 'user']), 'api');
        $middleware = new RoleMiddleware;
        $request = Request::create('/');
        $next = fn () => new Response('', 204);
        $this->assertSame(403, $middleware->handle($request, $next, 'admin')->getStatusCode());
        $this->assertSame(204, $middleware->handle($request, $next, 'admin', 'user')->getStatusCode());
        $this->actingAs(new User(['rol' => 'ADMIN']), 'api');
        $this->assertSame(403, $middleware->handle($request, $next, 'admin')->getStatusCode());
        $this->actingAs(new User(['rol' => 'admin']), 'api');
        $this->assertSame(204, $middleware->handle($request, $next, 'admin')->getStatusCode());
    }
}
