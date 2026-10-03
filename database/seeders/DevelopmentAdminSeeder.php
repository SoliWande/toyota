<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use InvalidArgumentException;
use LogicException;

class DevelopmentAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Development admin seeding is restricted to local/testing.');
        }

        $email = config('development.admin.email');
        $password = config('development.admin.password');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! is_string($password) || strlen($password) < 12) {
            throw new InvalidArgumentException('Set DEV_ADMIN_EMAIL and DEV_ADMIN_PASSWORD (at least 12 characters) in the environment.');
        }

        $existing = User::where('email', $email)->first();
        if ($existing !== null) {
            if ($existing->role !== UserRole::Admin) {
                throw new LogicException('Development admin email belongs to a non-admin account.');
            }

            return; // Never overwrite existing credentials or account status.
        }

        $admin = new User([
            'name' => config('development.admin.name'),
            'email' => $email,
            'password' => $password,
        ]);
        $admin->role = UserRole::Admin;
        $admin->status = UserStatus::Active;
        $admin->email_verified_at = now();
        $admin->save();
    }
}
