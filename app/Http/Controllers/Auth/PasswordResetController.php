<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate(['email' => ['required', 'string', 'not_regex:/[\x00-\x1F\x7F]/', 'email', 'max:255']]);
        if (! $this->deliveryConfigured(config('mail.default'))) {
            return back()->with('success', 'Chức năng gửi email hiện chưa sẵn sàng. Vui lòng liên hệ quản trị viên để được hỗ trợ.')
                ->with('password_reset_unavailable', true);
        }
        try {
            Password::sendResetLink(['email' => $data['email']]);
        } catch (TransportExceptionInterface|\InvalidArgumentException $error) {
            // Never log recipient/token, transport credentials or the exception's message.
            Log::warning('Password reset mail transport unavailable.');
        }

        // Identical for sent, unknown user, throttled broker and delivery failure.
        return back()->with('success', 'Yêu cầu đã được tiếp nhận. Nếu email đã đăng ký và dịch vụ gửi thư khả dụng, bạn sẽ nhận được liên kết đặt lại mật khẩu.');
    }

    private function deliveryConfigured(?string $name, array $visited = []): bool
    {
        if (! $name || in_array($name, $visited, true)
            || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $mailer = config('mail.mailers.'.$name, []);
        $transport = $mailer['transport'] ?? null;
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $mailer['mailers'] ?? [];

            return $children !== [] && collect($children)->every(fn ($child) => $this->deliveryConfigured($child, [...$visited, $name]));
        }
        if ($transport === 'smtp') {
            return filled($mailer['url'] ?? null) || (filled($mailer['host'] ?? null) && (int) ($mailer['port'] ?? 0) > 0);
        }
        if ($transport === 'sendmail') {
            return is_executable(strtok($mailer['path'] ?? '', ' '));
        }

        return in_array($transport, ['ses', 'ses-v2', 'postmark', 'mailgun'], true);
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
