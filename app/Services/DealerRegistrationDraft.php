<?php

namespace App\Services;

use App\Models\Dealer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final class DealerRegistrationDraft
{
    public const VEHICLES = ['Veloz', 'Hilux', 'Fortuner', 'FJ'];

    private function key(Request $request, Dealer $dealer): string
    {
        return 'dealer-draft:'.RegistrationPhone::key($request->session()->getId().'|'.$dealer->getKey());
    }

    public function read(Request $request, Dealer $dealer): ?array
    {
        $key = $this->key($request, $dealer);
        $encrypted = Cache::store('file')->get($key);
        if (! is_string($encrypted)) {
            return null;
        }
        try {
            $draft = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            Cache::store('file')->forget($key);

            return null;
        }
        if (! $dealer->is_active || $draft['expires_at'] <= now()->timestamp) {
            Cache::store('file')->forget($key);

            return null;
        }

        return $draft;
    }

    public function write(Request $request, Dealer $dealer, array $values, array $errors = [], bool $duplicate = false): void
    {
        $expires = now()->addMinutes(30);
        $draft = ['values' => $values, 'errors' => $errors, 'duplicate' => $duplicate,
            'complete' => $errors === [], 'expires_at' => $expires->timestamp];
        if (! Cache::store('file')->put($this->key($request, $dealer), Crypt::encryptString(json_encode($draft, JSON_THROW_ON_ERROR)), $expires)) {
            throw new \RuntimeException('Unable to persist registration draft.');
        }
    }
}
