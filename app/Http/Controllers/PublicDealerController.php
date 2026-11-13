<?php

namespace App\Http\Controllers;

use App\Enums\LeaderboardPeriod;
use App\Models\Dealer;
use App\Services\DealerCallPhone;
use App\Services\DealerDirectoryProvinces;
use App\Services\DealerProfile;
use App\Services\DealerRegistrationDraft;
use App\Services\LeaderboardService;
use App\Services\ToyotaRealisticDemo;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

class PublicDealerController extends Controller
{
    // Directory projection excludes account relations and internal source data.
    private const PUBLIC_FIELDS = ['id', 'slug', 'name', 'code', 'province', 'address', 'phone', 'website', 'facebook_url', 'zalo_url', 'opening_hours', 'is_active'];

    public function index(Request $request, LeaderboardService $leaderboard, DealerDirectoryProvinces $provinceOptions): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'province' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['members', 'name', 'rank'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $filters['sort'] ??= 'members';
        $filters['direction'] ??= $filters['sort'] === 'members' ? 'desc' : 'asc';
        $query = Dealer::query()->select(array_map(fn ($field) => 'dealers.'.$field, array_diff(self::PUBLIC_FIELDS, ['phone'])))
            ->leftJoinSub($leaderboard->dealers(LeaderboardPeriod::AllTime), 'standing', 'standing.id', '=', 'dealers.id')
            // Operator-confirmed B14 definition: members are approved profiles.
            // Keep the existing eligibility, historical attribution and demo exclusion.
            ->selectRaw('COALESCE(standing.score, 0) AS score, COALESCE(standing.score, 0) AS members_count, standing.rank AS `rank`')
            ->where('dealers.is_active', true);
        if (filled($filters['q'] ?? null)) {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($query) => $query->whereRaw("dealers.name LIKE ? ESCAPE '!'", [$search])
                ->orWhereRaw("dealers.province LIKE ? ESCAPE '!'", [$search]));
        }

        $groups = null;
        if (filled($filters['province'] ?? null)) {
            $groups = $provinceOptions->groups();
            $filters['province'] = $provinceOptions->selected($filters['province'], $groups);
            if ($filters['province'] !== '') {
                $query->whereIn('dealers.province', $groups[$filters['province']] ?? []);
            }
        }

        $columns = ['members' => 'members_count', 'name' => 'dealers.name', 'rank' => 'standing.rank'];
        if ($filters['sort'] === 'rank') {
            $query->orderByRaw('standing.rank IS NULL ASC'); // Unranked always last, never rank zero.
        }
        $query->orderBy($columns[$filters['sort']], $filters['direction']);
        if ($filters['sort'] === 'members') {
            $query->orderBy('dealers.name');
        }
        $query->orderBy('dealers.id');
        $state = array_filter(array_intersect_key($filters, array_flip(['q', 'province', 'sort', 'direction'])), fn ($value) => $value !== null && $value !== '');
        $dealers = $query->paginate(20)->appends($state);
        $groups ??= $provinceOptions->groups();

        return view('dealers.index', [
            'dealers' => $dealers,
            'filters' => $filters,
            'provinces' => collect(array_keys($groups)),
        ]);
    }

    public function competition(int $dealer, LeaderboardService $leaderboard, DealerCallPhone $phones): Response
    {
        $model = Dealer::select([...self::PUBLIC_FIELDS, 'photo_path'])->findOrFail($dealer);
        $photoUrl = $model->photo_path && Storage::disk('dealer_photos')->exists($model->photo_path) ? route('dealers.photo', $model->id) : null;
        unset($model->photo_path);
        $phone = $phones->forDealer($model);
        if ($phone === null) {
            unset($model->phone);
        }
        $standing = DB::query()->fromSub($leaderboard->dealers(LeaderboardPeriod::AllTime), 'standing')->where('id', $model->id)->first();
        $month = CarbonImmutable::now(config('app.timezone'))->startOfMonth();
        $months = [];
        for ($offset = 2; $offset >= 0; $offset--) {
            $start = $month->subMonths($offset);
            // Reuse the existing approved/attribution/demo filters and date bounds.
            $months[] = ['label' => 'Tháng '.$start->month, 'period' => $start->format('Y-m'),
                'score' => (int) ($leaderboard->dealerScores($start, $start->addMonth())->where('dealers.id', $model->id)->first()?->score ?? 0)];
        }

        return response()->view('dealers.competition', ['dealer' => $model, 'standing' => $standing,
            'months' => $months, 'phone' => $phone, 'photoUrl' => $photoUrl])->header('Cache-Control', 'no-store, private');
    }

    public function legacy(int $dealer, DealerProfile $profile): RedirectResponse
    {
        return redirect()->to($profile->url(Dealer::select(['id', 'slug'])->findOrFail($dealer)), 301);
    }

    public function show(string $slug, LeaderboardService $leaderboard, Request $request, DealerRegistrationDraft $drafts): Response
    {
        // Historical ranking entries may belong to an inactive Dealer.
        $dealer = Dealer::query()->select([...self::PUBLIC_FIELDS, 'photo_path'])->where('slug', $slug)->firstOrFail();
        $standing = null;
        $dealerOwnerCount = null;
        try {
            // Verify first, including when demo installation/metrics flags are disabled.
            $ownedSubmissionIds = app(ToyotaRealisticDemo::class)->ownedSubmissionIds();
            $standing = DB::query()->fromSub($leaderboard->dealers(LeaderboardPeriod::AllTime), 'standing')->where('id', $dealer->id)->first();
            // D01 keeps the existing approved count and historical Dealer attribution.
            $dealerOwnerCount = (int) ($leaderboard->dealerScores()->where('dealers.id', $dealer->id)
                ->whereNotIn('submissions.id', $ownedSubmissionIds)
                ->first()?->score ?? 0);
        } catch (LogicException) {
            // Unknown metrics are not zero; never publish a partially verified result.
            $standing = null;
            $dealerOwnerCount = null;
            Log::warning('Dealer landing metrics unavailable; demo verification requires operator review.', [
                'dealer_id' => $dealer->id,
                'reason' => 'demo_verification_failed',
            ]);
        }

        $draft = $drafts->read($request, $dealer);

        return response()->view('dealers.show', [
            'dealer' => $dealer, 'standing' => $standing, 'dealerOwnerCount' => $dealerOwnerCount,
            'stepOneValues' => $draft['values'] ?? [], 'stepOneErrors' => $draft['errors'] ?? [],
            'duplicatePhone' => $draft['duplicate'] ?? false,
        ])->header('Cache-Control', 'no-store, private');
    }
}
