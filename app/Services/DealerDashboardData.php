<?php

namespace App\Services;

use App\Enums\AwardWinnerType;
use App\Enums\LeaderboardPeriod;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AwardWinner;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DealerDashboardData
{
    public function currentDealer(User $user): Dealer
    {
        abort_unless($user->role === UserRole::Dealer && $user->status === UserStatus::Active && $user->dealer_id !== null, 403);

        return $user->dealer()->firstOrFail();
    }

    public function standings(Dealer $dealer, LeaderboardService $leaderboard): array
    {
        $standings = [];
        foreach (LeaderboardPeriod::cases() as $period) {
            $standings[$period->value] = DB::query()->fromSub($leaderboard->dealers($period), 'standing')
                ->where('id', $dealer->id)->first() ?? (object) ['score' => 0, 'rank' => null];
        }

        return $standings;
    }

    public function sales(Dealer $dealer): Builder
    {
        return $dealer->sales()->getQuery()->select(['id', 'dealer_id', 'role', 'name', 'email', 'phone', 'status', 'avatar_path', 'public_slug']);
    }

    public function customers(Dealer $dealer): Builder
    {
        // Attribution belongs to the submission, independently of the Sales account's current Dealer.
        return $dealer->submissions()->getQuery()->select([
            'id', 'dealer_id', 'sales_id', 'customer_name', 'phone', 'facebook_url', 'facebook_url_normalized',
            'status', 'submitted_at', 'reviewed_at', 'address_line', 'province_code', 'province_name', 'ward_code', 'ward_name',
            'vehicle_model', 'vehicle_description', 'source', 'vehicle_color', 'first_registration_year', 'license_plate', 'evidence_image_path',
        ])->with('sales:id,name,dealer_id,status,public_slug');
    }

    public function awards(Dealer $dealer): Builder
    {
        return AwardWinner::query()->select(['id', 'award_id', 'dealer_id', 'rank', 'score', 'dealer_name_snapshot', 'dealer_code_snapshot'])
            ->where('winner_type', AwardWinnerType::Dealer->value)->where('dealer_id', $dealer->id)
            ->whereHas('award', fn ($query) => $query->whereNotNull('published_at'))
            ->with('award:id,period_type,period_start,period_end,published_at')->orderByDesc('award_id')->orderByDesc('id');
    }
}
