<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoginAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        // Also covers Sanctum tokens and legacy sessions; do not cast a legacy role.
        $authenticated = $request->user();
        $account = $authenticated instanceof User
            ? User::loginAccounts()->whereKey($authenticated->getAuthIdentifier())->first()
            : null;
        abort_if($account === null, 403, 'Tài khoản không được phép đăng nhập hệ thống.');
        if ($token = $authenticated->currentAccessToken()) {
            $account->withAccessToken($token);
        }
        auth()->guard()->setUser($account);

        return $next($request);
    }
}
