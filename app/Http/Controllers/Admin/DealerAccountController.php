<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DealerAccountRequest;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DealerAccountController extends Controller
{
    public function edit(Dealer $dealer): View
    {
        $this->authorize('update', $dealer);

        return view('admin.dealers.account', ['dealer' => $dealer, 'manager' => $dealer->manager()->first()]);
    }

    public function store(DealerAccountRequest $request, Dealer $dealer): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $dealer) {
                $dealer = Dealer::whereKey($dealer->id)->lockForUpdate()->firstOrFail();
                if ($dealer->manager()->exists()) {
                    throw ValidationException::withMessages(['email' => 'Đại lý đã có tài khoản quản lý.']);
                }
                $manager = new User($request->safe()->only(['name', 'email', 'password']));
                $manager->role = UserRole::Dealer;
                $manager->status = $request->validated('status');
                $manager->dealer()->associate($dealer);
                $manager->save();
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
            throw ValidationException::withMessages(['email' => 'Email hoặc tài khoản quản lý đại lý đã tồn tại.']);
        }

        return back()->with('success', 'Đã tạo tài khoản Dealer.');
    }

    public function update(DealerAccountRequest $request, Dealer $dealer): RedirectResponse
    {
        DB::transaction(function () use ($request, $dealer) {
            Dealer::whereKey($dealer->id)->lockForUpdate()->firstOrFail();
            $manager = $dealer->manager()->lockForUpdate()->firstOrFail();
            $manager->name = $request->validated('name');
            $manager->status = $request->validated('status');
            $manager->save();
        });

        return back()->with('success', 'Đã cập nhật tài khoản Dealer.');
    }
}
