<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'dealers' => Dealer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $user = DB::transaction(function () use ($data) {
                $dealer = Dealer::whereKey($data['dealer_id'])->where('is_active', true)->lockForUpdate()->first();
                if ($dealer === null) {
                    throw ValidationException::withMessages(['dealer_id' => 'Đại lý đang ngừng nhận đăng ký.']);
                }

                $user = new User([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);
                $user->role = UserRole::Sales;
                $user->status = UserStatus::Pending;
                $user->dealer()->associate($dealer);
                $user->save();

                return $user;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            throw ValidationException::withMessages(['email' => 'Email này đã được đăng ký.']);
        }

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('account.status')->with('success', 'Đăng ký thành công. Tài khoản đang chờ quản trị viên duyệt.');
    }
}
