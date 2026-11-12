<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateAwardDraft;
use App\Enums\AwardPeriod;
use App\Enums\AwardWinnerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AwardDraftRequest;
use App\Models\Award;
use App\Models\CallAuditBatch;
use App\Services\AwardPublicationReadiness;
use App\Services\AwardResults;
use App\Services\CallAudit\BatchReadModel;
use App\Services\CallAudit\ReadUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AwardController extends Controller
{
    public function index(Request $request, AwardResults $results): View
    {
        $this->authorize('viewAny', Award::class);
        $data = $request->validate(['period_type' => ['nullable', Rule::enum(AwardPeriod::class)], 'page' => ['nullable', 'integer', 'min:1'], 'selected' => ['nullable', 'integer', 'min:1']]);
        $query = Award::query();
        if (! empty($data['period_type'])) {
            $query->where('period_type', $data['period_type']);
        }

        // The C07 rail has no pagination controls: all existing periods remain reachable.
        $awards = $query->orderByDesc('period_start')->orderByDesc('id')->get();
        $selected = $awards->firstWhere('id', $data['selected'] ?? null) ?? $awards->first();
        $dealers = collect();
        $callUrl = null;
        $auditDealers = collect();
        $publication = null;
        $callCommand = null;
        if ($selected) {
            $this->authorize('view', $selected);
            $dealers = $selected->published_at === null
                ? $results->preview($selected)['dealers']
                : $selected->winners()->where('winner_type', AwardWinnerType::Dealer->value)->orderBy('rank')->limit(3)->get();
            $auditBatches = CallAuditBatch::where('award_id', $selected->id)->where('purpose', 'award_top3')
                ->where('lifecycle_state', 'frozen')->orderBy('generation')->get();
            $callBatch = $auditBatches->last();
            foreach ($auditBatches as $auditBatch) {
                $audit = app(BatchReadModel::class)->show($request->user(), $auditBatch->id,
                    ['purpose' => 'award_top3', 'month' => $auditBatch->monthly_scope_key]);
                $auditDealers = $auditDealers->union(collect($audit['data']['dealers'])->keyBy('id'));
                if ($audit['data']['progress']['needs_call_count'] || $audit['data']['progress']['recall_count']) {
                    $callBatch = $auditBatch;
                }
            }
            if ($selected->published_at === null && $selected->period_type === AwardPeriod::Monthly) {
                try {
                    $publication = app(AwardPublicationReadiness::class)->read($request->user(), $selected->id);
                    $callUrl = route('admin.call-audits.workspace', ['month' => $selected->period_start->format('Y-m'), 'purpose' => 'award_top3', 'batch' => $callBatch?->id]);
                    if ($auditBatches->isEmpty()) {
                        $callCommand = route('admin.call-audits.freeze');
                    } elseif ($publication['supplemental_dealer_ids'] ?? []) {
                        $callCommand = route('admin.awards.supplemental', $selected);
                    }
                    if (array_intersect($publication['blockers'], ['period_not_ended', 'pending_review_in_month', 'review_timestamp_outside_cutoff', 'monthly_cohort_conflict'])) {
                        $callUrl = null;
                    }
                } catch (ReadUnavailable|\Illuminate\Contracts\Encryption\DecryptException) {
                    $publication = null;
                    $callUrl = null;
                }
            }

        }

        return view('admin.awards.index', compact('awards', 'selected', 'dealers', 'callUrl', 'auditDealers', 'publication', 'callCommand') + ['filters' => $data]);
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
