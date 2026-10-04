<?php

namespace App\Enums;

enum LeaderboardPeriod: string
{
    case Week = 'week';
    case Month = 'month';
    case AllTime = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Week => 'Tuần này',
            self::Month => 'Tháng này',
            self::AllTime => 'Toàn chương trình',
        };
    }
}
