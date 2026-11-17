<?php

namespace App\Services;

use App\Models\Dealer;

class DealerCallPhone
{
    // Compatibility for single-number consumers; UI lists use allForDealer().
    public function forDealer(Dealer $dealer): ?string
    {
        return $this->allForDealer($dealer)[0] ?? null;
    }

    /** @return list<string> */
    public function allForDealer(Dealer $dealer): array
    {
        if (! $dealer->exists || ! $dealer->is_active) {
            return [];
        }

        return $this->parse($dealer->phone);
    }

    /** @return list<string> */
    public function parse(?string $rawPhone): array
    {
        if (! is_string($rawPhone)) {
            return [];
        }

        $phones = [];
        // Whitespace formats a single number; only explicit list separators split it.
        foreach (preg_split('/[,;\/\r\n\x{2028}\x{2029}]+/u', $rawPhone) ?: [] as $candidate) {
            $candidate = preg_replace('/[\s\p{Z}]+/u', '', $candidate);
            $phone = RegistrationPhone::normalize($candidate);
            // Vietnamese mobile, geographic landline and service numbers only.
            if (preg_match('/\A(?:0[35789][0-9]{8}|02[0-9]{9}|1[89]00[0-9]{4,6})\z/', $phone)
                && ! in_array($phone, $phones, true)) {
                $phones[] = $phone;
            }
        }

        return $phones;
    }
}
