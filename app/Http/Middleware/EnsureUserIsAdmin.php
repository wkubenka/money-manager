<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->user()?->email, config('admin.emails'))) {
            abort(403);
        }

        return $next($request);
    }
}
