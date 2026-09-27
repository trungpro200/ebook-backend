<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user(), 401, 'Unauthenticated.');
        abort_unless(in_array($request->user()->role, $roles, true), 403, 'Bạn không có quyền thực hiện thao tác này.');

        return $next($request);
    }
}
