<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\ProfileRequest;
use App\Models\User;
use App\Services\SalesAvatars;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load('dealer');

        return view($user->role === UserRole::Dealer ? 'profile.dealer' : 'profile.edit', ['user' => $user]);
    }

    public function avatar(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Dealer, 403);
        $disk = Storage::disk('dealer_avatars');
        abort_unless($user->avatar_path && $disk->exists($user->avatar_path), 404);

        return $disk->response($user->avatar_path, 'avatar.jpg', [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(ProfileRequest $request, SalesAvatars $avatars): RedirectResponse
    {
        $disk = $request->user()->role === UserRole::Dealer ? 'dealer_avatars' : 'sales_avatars';
        try {
            $avatar = $request->hasFile('avatar') ? $avatars->store($request->file('avatar')->getPathname(), $disk) : null;
        } catch (RuntimeException $exception) {
            if ($request->user()->role !== UserRole::Dealer) {
                throw $exception;
            }
            report($exception);
            throw ValidationException::withMessages(['avatar' => 'Không thể lưu ảnh đại diện. Vui lòng thử lại.']);
        }
        try {
            DB::transaction(function () use ($request, $avatar, $disk, $avatars) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->status === UserStatus::Active, 403);
                abort_unless($user->role === $request->user()->role, 403);
                $user->name = $request->validated('name');
                if ($user->role === UserRole::Sales) {
                    $user->phone = $request->validated('phone');
                }
                if ($user->role === UserRole::Dealer && array_key_exists('phone', $request->validated())) {
                    $user->phone = $request->validated('phone');
                }
                if (in_array($user->role, [UserRole::Sales, UserRole::Dealer], true)) {
                    if ($avatar !== null) {
                        $previous = $user->avatar_path;
                        $user->avatar_path = $avatar;
                        DB::afterCommit(fn () => rescue(fn () => $avatars->deleteManaged($previous, $disk)));
                    }
                }
                $user->save();
            });
        } catch (Throwable $exception) {
            if ($avatar !== null) {
                rescue(fn () => $avatars->deleteManaged($avatar, $disk));
            }
            throw $exception;
        }

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)],
            'email' => ['missing'], 'dealer_id' => ['missing'], 'role' => ['missing'], 'status' => ['missing'],
            'user_id' => ['missing'], 'id' => ['missing'],
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->status === UserStatus::Active, 403);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không đúng.']);
            }
            $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
            Auth::guard('web')->setUser($user);
        });
        $request->session()->regenerate();

        return back()->with('success', 'Đã đổi mật khẩu.');
    }
}
