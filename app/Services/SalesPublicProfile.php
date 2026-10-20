<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SalesPublicProfile
{
    public function find(string $slug): User
    {
        return User::query()->select(['id', 'public_slug', 'name', 'phone', 'dealer_id', 'avatar_path'])
            ->where('public_slug', $slug)->where('role', UserRole::Sales->value)->where('status', UserStatus::Active->value)
            ->with('dealer:id,name,slug')->firstOrFail();
    }

    public function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone ?? '');

        // Reveal at most three trailing digits; short or malformed values reveal nothing.
        return strlen($digits) >= 9 && strlen($digits) <= 15 ? '••• ••• '.substr($digits, -3) : 'Chưa công khai';
    }

    public function hasPublicAvatar(User $sales): bool
    {
        // Previously generated demo portraits stay internal, even if the demo display name changes.
        // This additional lookup never enters the view projection or a public JSON response.
        $email = User::whereKey($sales->id)->value('email');

        return $sales->avatar_path !== null
            && (! preg_match('/^demo\.sales\.[0-9]{2}@example\.test$/D', $email ?? '')
                || str_starts_with($sales->avatar_path, 'uploaded-'));
    }

    public function data(User $sales): array
    {
        $approved = DB::table('customer_submissions')->where('sales_id', $sales->id)
            ->where('source', 'sales')->where('status', SubmissionStatus::Approved->value);
        $month = CarbonImmutable::now(config('app.timezone'))->startOfMonth();
        // DATETIME follows the application's timezone, like LeaderboardService.
        $counts = (clone $approved)->where('submitted_at', '>=', $month->subMonths(2)->format('Y-m-d H:i:s'))
            ->where('submitted_at', '<', $month->addMonth()->format('Y-m-d H:i:s'))
            ->selectRaw("DATE_FORMAT(submitted_at, '%Y-%m') AS month, COUNT(*) AS total")->groupBy('month')->pluck('total', 'month');
        $months = [];
        foreach ([0, 1, 2] as $offset) {
            $date = $month->subMonths($offset);
            $months[] = ['label' => $date->format('m/Y'), 'count' => (int) $counts->get($date->format('Y-m'), 0)];
        }

        // Explicit, scalar-only public projection. Never pass a User model or customer rows to Blade.
        return [
            'slug' => $sales->public_slug, 'name' => $sales->name, 'maskedPhone' => $this->maskPhone($sales->phone),
            'dealerName' => $sales->dealer?->name, 'dealerSlug' => $sales->dealer?->slug,
            'dealerUrl' => $sales->dealer ? app(DealerProfile::class)->url($sales->dealer) : null,
            'hasAvatar' => $this->hasPublicAvatar($sales), 'approvedTotal' => (clone $approved)->count(), 'months' => $months,
        ];
    }
}
