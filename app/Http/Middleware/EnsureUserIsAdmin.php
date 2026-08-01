<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // 404 y no 403: no confirmamos al atacante que /admin existe.
        abort_unless($request->user()?->is_admin, 404);

        return $next($request);
    }
}
