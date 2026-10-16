<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless(UserRole::tryFrom($role) !== null && ($request->user()?->getAttributes()['role'] ?? null) === $role, 403);

        return $next($request);
    }
}
