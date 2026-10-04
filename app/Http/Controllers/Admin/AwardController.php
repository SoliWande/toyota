<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateAwardDraft;
use App\Enums\AwardPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AwardDraftRequest;
use App\Models\Award;
use App\Services\AwardResults;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AwardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Award::class);
        $data = $request->validate(['period_type' => ['nullable', Rule::enum(AwardPeriod::class)], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = Award::query();
        if (! empty($data['period_type'])) {
            $query->where('period_type', $data['period_type']);
        }

        return view('admin.awards.index', ['awards' => $query->orderByDesc('period_start')->orderByDesc('id')->paginate(20)->withQueryString()]);
    }

    public function store(AwardDraftRequest $request, CreateAwardDraft $create): RedirectResponse
    {
        $data = $request->validated();
        $award = $create->execute($request->user(), AwardPeriod::from($data['period_type']), CarbonImmutable::parse($data['period_date'], config('app.timezone')), $data['title'] ?? null);

        return redirect()->route('admin.awards.show', $award);
    }

    public function show(Award $award, AwardResults $results): View
    {
        $this->authorize('view', $award);
        $preview = $award->published_at === null ? $results->preview($award) : null;

        return view('admin.awards.show', [
            'award' => $award->load(['winners' => fn ($query) => $query->orderBy('rank'), 'publisher']),
            'preview' => $preview,
            'previewHash' => $preview === null ? null : $results->fingerprint($award, $preview),
        ]);
    }
}
