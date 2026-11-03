<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Knowledge of both identities permits only this minimal, masked existence result. */
final class RegistrationLookup
{
    public function find(string $phone, string $plate, ?\Closure $resolve = null): ?array
    {
        $phone = RegistrationPhone::normalize($phone);
        $plate = SubmissionIntake::plate($plate);
        $match = null;
        // Historical formats require canonical comparison; never select the most recent collision.
        foreach (DB::table('customer_submissions')->select(['id', 'phone', 'phone_normalized',
            'license_plate', 'customer_name', 'vehicle_model', 'vehicle_description'])->lazyById(500) as $row) {
            $phones = array_values(array_unique(array_filter(array_map(RegistrationPhone::normalize(...),
                [$row->phone ?? '', $row->phone_normalized ?? '']), fn ($value) => $value !== '')));
            if (! in_array($phone, $phones, true) || SubmissionIntake::plate($row->license_plate) !== $plate) {
                continue;
            }
            // Inconsistent aliases or multiple matches cannot safely identify one registration.
            if (count($phones) !== 1 || $match !== null) {
                return ['kind' => 'not_found'];
            }
            $match = ['id' => $row->id, 'summary' => $this->summary($row->customer_name, $row->vehicle_description ?? $row->vehicle_model)];
        }

        return $match === null ? null : ($resolve ? $resolve($match['id'], $match['summary']) : $match['summary']);
    }

    public function summary(string $name, ?string $vehicle): array
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $masked = implode(' ', array_map(fn ($part) => mb_substr($part, 0, 1).'**', $parts));
        $vehicles = ['veloz' => 'Veloz', 'hilux' => 'Hilux', 'fortuner' => 'Fortuner', 'fj' => 'FJ'];

        return ['kind' => 'found', 'masked_name' => $masked ?: '***',
            'vehicle' => $vehicles[mb_strtolower(trim($vehicle ?? ''))] ?? 'Toyota'];
    }
}
