<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->status !== UserStatus::Active) {
            if ($request->expectsJson()) {
                abort(403, 'Tài khoản chưa được phép sử dụng hệ thống.');
            }

            return redirect()->route('account.status');
        }

        return $next($request);
    }
}
