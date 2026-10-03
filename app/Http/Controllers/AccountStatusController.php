<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->status === UserStatus::Active) {
            return redirect()->route($request->user()->homeRoute());
        }

        return view('auth.account-status');
    }
}
