<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateDealerAccount
{
    public function execute(User $admin, array $data): User
    {
        Gate::forUser($admin)->authorize('create', User::class);

        try {
            return DB::transaction(function () use ($admin, $data) {
                $actor = User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('create', User::class);
                // The same Dealer lock is shared by self-registration. The UNIQUE
                // generated key remains the final guard against any other writer.
                $dealer = Dealer::whereKey($data['dealer_id'])->where('is_active', true)->lockForUpdate()->first();
                if ($dealer === null) {
                    throw ValidationException::withMessages(['dealer_id' => 'Đại lý không tồn tại hoặc đang ngừng hoạt động.']);
                }
                if ($dealer->loginAccount()->exists()) {
                    throw ValidationException::withMessages(['dealer_id' => 'Đại lý đã có tài khoản. Không thể tạo thêm.']);
                }

                $account = new User([
                    'name' => $data['name'], 'email' => mb_strtolower(trim($data['email'])), 'password' => $data['password'],
                ]);
                $account->phone = $data['phone'] ?? null;
                $account->role = UserRole::Dealer;
                $account->status = UserStatus::Pending;
                $account->dealer()->associate($dealer);
                $account->save();

                return $account;
            }, 3);
        } catch (QueryException $error) {
            if (($error->errorInfo[1] ?? null) !== 1062) {
                throw $error;
            }
            if (str_contains($error->errorInfo[2] ?? '', 'users_one_dealer_account_unique')) {
                throw ValidationException::withMessages(['dealer_id' => 'Đại lý đã có tài khoản. Không thể tạo thêm.']);
            }
            throw ValidationException::withMessages(['email' => 'Email này đã được đăng ký.']);
        }
    }
}
