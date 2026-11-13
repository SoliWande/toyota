<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DealerIntroductionContact
{
    public function managerFor(Dealer $dealer): ?User
    {
        if (! $dealer->exists || ! $dealer->is_active) {
            return null;
        }
        $mapping = config('dealer-registration.manager_contacts', []);
        $id = is_array($mapping) ? ($mapping[$dealer->slug] ?? null) : null;
        if ((! is_int($id) && ! is_string($id)) || ! ctype_digit((string) $id) || (int) $id < 1) {
            return null;
        }

        return User::query()->whereKey($id)->where('dealer_id', $dealer->id)
            ->where('role', UserRole::Dealer)->where('status', UserStatus::Active)
            ->first(['id', 'dealer_id', 'role', 'status', 'phone', 'avatar_path']);
    }

    /** @return array{disk: string, path: string, manager: bool}|null */
    public function photoFor(Dealer $dealer): ?array
    {
        if ($dealer->exists && filled($dealer->photo_path) && Storage::disk('dealer_photos')->exists($dealer->photo_path)) {
            return ['disk' => 'dealer_photos', 'path' => $dealer->photo_path, 'manager' => false];
        }
        $manager = $this->managerFor($dealer);
        if ($manager && filled($manager->avatar_path) && Storage::disk('dealer_avatars')->exists($manager->avatar_path)) {
            return ['disk' => 'dealer_avatars', 'path' => $manager->avatar_path, 'manager' => true];
        }

        return null;
    }
}
