<?php

namespace App\Http\Controllers;

use App\Enums\LeaderboardPeriod;
use App\Models\Dealer;
use App\Services\DealerProfile;
use App\Services\LeaderboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicDealerController extends Controller
{
    // Directory projection excludes account relations and internal source data.
    private const PUBLIC_FIELDS = ['id', 'slug', 'name', 'code', 'province', 'address', 'phone', 'website', 'facebook_url', 'zalo_url', 'opening_hours', 'is_active'];

    public function index(Request $request, LeaderboardService $leaderboard): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = Dealer::query()->select(array_map(fn ($field) => 'dealers.'.$field, self::PUBLIC_FIELDS))
            ->leftJoinSub($leaderboard->dealers(LeaderboardPeriod::AllTime), 'standing', 'standing.id', '=', 'dealers.id')
            ->selectRaw('COALESCE(standing.score, 0) AS score, standing.rank AS `rank`')
            ->where('dealers.is_active', true);
        if (filled($filters['q'] ?? null)) {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($query) => $query->whereRaw("dealers.name LIKE ? ESCAPE '!'", [$search])
                ->orWhereRaw("dealers.province LIKE ? ESCAPE '!'", [$search]));
        }

        return view('dealers.index', [
            'dealers' => $query->orderByDesc('score')->orderBy('dealers.name')->orderBy('dealers.id')->paginate(20)->appends($request->only('q')),
            'filters' => $filters,
        ]);
    }

    public function legacy(int $dealer, DealerProfile $profile): RedirectResponse
    {
        return redirect()->to($profile->url(Dealer::select(['id', 'slug'])->findOrFail($dealer)), 301);
    }

    public function show(string $slug, LeaderboardService $leaderboard): Response
    {
        // Historical ranking entries may belong to an inactive Dealer.
        $dealer = Dealer::query()->select([...self::PUBLIC_FIELDS, 'photo_path'])->where('slug', $slug)->firstOrFail();
        $standing = DB::query()->fromSub($leaderboard->dealers(LeaderboardPeriod::AllTime), 'standing')->where('id', $dealer->id)->first();

        return response()->view('dealers.show', compact('dealer', 'standing'))->header('Cache-Control', 'no-store, private');
    }
}
