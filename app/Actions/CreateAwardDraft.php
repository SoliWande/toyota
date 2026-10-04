<?php

namespace App\Actions;

use App\Enums\AwardPeriod;
use App\Models\Award;
use App\Models\User;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class CreateAwardDraft
{
    public function __construct(private LeaderboardService $leaderboard) {}

    public function execute(User $admin, AwardPeriod $period, CarbonImmutable $date, ?string $title = null): Award
    {
        Gate::forUser($admin)->authorize('create', Award::class);
        [$start, $end] = $this->leaderboard->bounds($period->leaderboardPeriod(), $date);

        return Award::firstOrCreate([
            'period_type' => $period,
            'period_start' => $start,
            'period_end' => $end,
        ], ['title' => $title ?? 'Vinh danh '.mb_strtolower($period->label()).' '.$start->format('d/m/Y').' – '.$end->subDay()->format('d/m/Y')]);
    }
}
