<?php

namespace App\Enums;

enum AwardPeriod: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function leaderboardPeriod(): LeaderboardPeriod
    {
        return match ($this) {
            self::Weekly => LeaderboardPeriod::Week,
            self::Monthly => LeaderboardPeriod::Month,
        };
    }

    public function label(): string
    {
        return $this === self::Weekly ? 'Tuần' : 'Tháng';
    }
}
