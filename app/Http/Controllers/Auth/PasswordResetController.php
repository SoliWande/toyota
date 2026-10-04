<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'not_regex:/[\x00-\x1F\x7F]/', 'email', 'max:255']]);
        Password::sendResetLink(['email' => mb_strtolower(trim($data['email']))]);

        return back()->with('success', 'Nếu email đã đăng ký, liên kết đặt lại mật khẩu sẽ được gửi đến email đó.');
    }

    public function create(Request $request, string $token): View
    {
        $request->validate(['email' => ['nullable', 'string', 'email', 'max:255']]);

        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'not_regex:/[\x00-\x1F\x7F]/', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', PasswordRule::min(8)],
            'dealer_id' => ['missing'], 'role' => ['missing'], 'status' => ['missing'],
        ]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Liên kết không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu liên kết mới.']);
        }

        return redirect()->route('login')->with('success', 'Đã đặt lại mật khẩu. Vui lòng đăng nhập.');
    }
}
