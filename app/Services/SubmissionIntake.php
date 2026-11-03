<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Guarded identity writes serialize on one InnoDB row until their outermost commit. */
final class SubmissionIntake
{
    public static function plate(?string $plate): string
    {
        return mb_strtoupper(preg_replace('/[\s.\-]+/u', '', $plate ?? ''));
    }

    public function locked(callable $callback, ?Connection $connection = null): mixed
    {
        $connection ??= DB::connection();

        return $connection->transaction(function () use ($connection, $callback) {
            if (! $connection->table('submission_intake_mutex')->where('id', 1)->lockForUpdate()->first()) {
                throw new \RuntimeException('Submission intake migration is required.');
            }

            return $callback();
        }, 3);
    }

    public function conflicts(?string $phone, ?string $plate = null, bool $locking = false, ?Connection $connection = null, ?int $exceptId = null, array $additionalPhones = []): array
    {
        $connection ??= DB::connection();
        $phones = array_values(array_unique(array_filter(array_map(RegistrationPhone::normalize(...), [$phone ?? '', ...$additionalPhones]), fn ($value) => $value !== '')));
        $plate = self::plate($plate);
        $query = $connection->table('customer_submissions')->whereIn('status', ['pending', 'approved'])
            ->select(['id', 'phone', 'phone_normalized', 'license_plate']);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($locking) {
            $query->lockForUpdate();
        } // Current read, including after waiting behind another transaction.
        $errors = [];
        foreach ($query->lazyById(500) as $record) {
            if (array_intersect($phones, [RegistrationPhone::normalize($record->phone ?? ''), RegistrationPhone::normalize($record->phone_normalized ?? '')])) {
                $errors['phone'] = ['Số điện thoại này đã đăng ký tham gia cộng đồng.'];
            }
            if ($plate !== '' && self::plate($record->license_plate) === $plate) {
                $errors['license_plate'] = ['Biển số xe này đã được đăng ký.'];
            }
            if (($phones === [] || isset($errors['phone'])) && ($plate === '' || isset($errors['license_plate']))) {
                break;
            }
        }

        return $errors;
    }

    public function assertAvailable(?string $phone, ?string $plate, Connection $connection): void
    {
        if ($errors = $this->conflicts($phone, $plate, true, $connection)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
