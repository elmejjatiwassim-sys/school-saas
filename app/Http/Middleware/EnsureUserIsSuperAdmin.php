<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_super_admin || ! ($user->is_active ?? true)) {
            abort(403, 'Unauthorized access: Super Admin privileges required.');
        }

        return $next($request);
    }
}
