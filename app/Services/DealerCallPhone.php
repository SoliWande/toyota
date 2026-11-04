<?php

namespace App\Services;

use App\Models\Dealer;

class DealerCallPhone
{
    public function forDealer(Dealer $dealer): ?string
    {
        if (! $dealer->exists || ! $dealer->is_active || ! is_string($dealer->phone)) {
            return null;
        }

        $phone = RegistrationPhone::normalize($dealer->phone);
        // Vietnamese mobile, geographic landline and service numbers only.
        if (! preg_match('/\A(?:0[35789][0-9]{8}|02[0-9]{9}|1[89]00[0-9]{4,6})\z/', $phone)) {
            return null;
        }

        $approved = config('dealer-registration.approved_call_phones', []);
        // Exact approval binds the public number to this Dealer, including demo Dealers.
        if (! is_array($approved) || ($approved[$dealer->slug] ?? null) !== $phone) {
            return null;
        }

        return $phone;
    }
}
