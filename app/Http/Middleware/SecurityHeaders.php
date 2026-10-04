<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->user() !== null || $request->is('login', 'register', 'forgot-password', 'reset-password', 'reset-password/*', 'account/*', 'sales/*', 'admin/*', 'api/user')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
