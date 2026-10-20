<?php

namespace App\Services;

class RegistrationPhone
{
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s().-]+/', '', $phone);
        if (str_starts_with($phone, '+84')) {
            $phone = '0'.substr($phone, 3);
        } elseif (str_starts_with($phone, '0084')) {
            $phone = '0'.substr($phone, 4);
        }

        return $phone;
    }

    public static function key(string $value): string
    {
        return hash_hmac('sha256', $value, config('app.key'));
    }
}
