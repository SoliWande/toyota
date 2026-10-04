<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ duyệt',
            self::Active => 'Hoạt động',
            self::Rejected => 'Từ chối',
            self::Blocked => 'Đã khóa',
        };
    }
}
