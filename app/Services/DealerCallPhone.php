<?php

namespace App\Services;

use App\Models\Dealer;

class DealerCallPhone
{
    public function forDealer(Dealer $dealer): ?string
    {
        if (! $dealer->exists || ! $dealer->is_active) {
            return null;
        }

        $managerFallback = blank($dealer->phone);
        $rawPhone = $managerFallback ? app(DealerIntroductionContact::class)->managerFor($dealer)?->phone : $dealer->phone;
        if (! is_string($rawPhone)) {
            return null;
        }
        $phone = RegistrationPhone::normalize($rawPhone);
        // Vietnamese mobile, geographic landline and service numbers only.
        if (! preg_match('/\A(?:0[35789][0-9]{8}|02[0-9]{9}|1[89]00[0-9]{4,6})\z/', $phone)) {
            return null;
        }

        // The explicit Dealer -> Manager map grants fallback publication only for this pair.
        if ($managerFallback) {
            return $phone;
        }

        $approved = config('dealer-registration.approved_call_phones', []);
        // Exact approval binds the public number to this Dealer, including demo Dealers.
        if (! is_array($approved) || ($approved[$dealer->slug] ?? null) !== $phone) {
            return null;
        }

        return $phone;
    }
}
