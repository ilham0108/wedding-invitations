<?php

namespace App\Http\Middleware;

use App\Enums\RoleSlug;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Usage: ->middleware('role:super_admin') or 'role:super_admin,client'.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = array_map(fn (string $role): RoleSlug => RoleSlug::from($role), $roles);

        abort_unless($request->user()?->hasRole(...$allowedRoles), 403);

        return $next($request);
    }
}
