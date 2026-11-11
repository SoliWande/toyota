<?php

namespace App\Services\CallAudit;

use App\Services\RegistrationPhone;

final class PhoneEligibility
{
    public const VERSION = 'qr_c03_phone_v1';

    public function inspect(?string $phone, ?string $normalized): array
    {
        $aliases = array_values(array_unique(array_filter([
            RegistrationPhone::normalize($phone ?? ''), RegistrationPhone::normalize($normalized ?? ''),
        ], fn ($value) => $value !== '')));
        if (count($aliases) > 1) {
            return ['status' => 'BLOCKED', 'code' => 'phone_alias_conflict', 'phone' => null, 'aliases' => $aliases];
        }
        $canonical = $aliases[0] ?? null;
        if ($canonical === null || ! preg_match('/^0[1-9][0-9]{8,9}$/D', $canonical)) {
            return ['status' => 'EXCLUDED', 'code' => 'invalid_phone', 'phone' => null, 'aliases' => $aliases];
        }

        return ['status' => 'READY', 'code' => null, 'phone' => $canonical, 'aliases' => $aliases];
    }
}
