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
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()->load('dealer')]);
    }

    public function update(ProfileRequest $request, SalesAvatars $avatars): RedirectResponse
    {
        $avatar = $request->hasFile('avatar') ? $avatars->store($request->file('avatar')->getRealPath()) : null;
        try {
            DB::transaction(function () use ($request, $avatar) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->status === UserStatus::Active, 403);
                $user->name = $request->validated('name');
                if ($user->role === UserRole::Sales) {
                    $user->phone = $request->validated('phone');
                    if ($avatar !== null) {
                        $previous = $user->avatar_path;
                        $user->avatar_path = $avatar;
                        if ($previous) {
                            DB::afterCommit(fn () => rescue(fn () => Storage::disk('sales_avatars')->delete($previous)));
                        }
                    }
                }
                $user->save();
            });
        } catch (Throwable $exception) {
            if ($avatar !== null) {
                Storage::disk('sales_avatars')->delete($avatar);
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
