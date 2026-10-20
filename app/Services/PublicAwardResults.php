<?php

namespace App\Services;

use App\Enums\AwardWinnerType;
use App\Models\Award;
use Illuminate\Database\Eloquent\Builder;

class PublicAwardResults
{
    public function query(): Builder
    {
        // Internal titles, publishers and Sales snapshots never enter public view data.
        return Award::query()
            ->select(['id', 'period_type', 'period_start', 'period_end', 'published_at'])
            ->whereNotNull('published_at')
            ->whereHas('winners', fn (Builder $query) => $query->where('winner_type', AwardWinnerType::Dealer->value));
    }

    public function withDealers(Builder $query): Builder
    {
        return $query->with(['winners' => fn ($winners) => $winners
            ->where('winner_type', AwardWinnerType::Dealer->value)
            ->select(['award_id', 'dealer_id', 'rank', 'score', 'dealer_name_snapshot', 'dealer_code_snapshot',
                'dealer_name_snapshot as winner_name_snapshot'])
            ->orderBy('rank')]);
    }

    public function latest(): ?Award
    {
        return $this->withDealers($this->query())->orderByDesc('published_at')->orderByDesc('id')->first();
    }

    public function find(int $id): Award
    {
        return $this->withDealers($this->query())->findOrFail($id);
    }
}
